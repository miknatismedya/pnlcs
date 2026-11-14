<?php

namespace Modules\Servers\Voyar;

use Illuminate\Validation\ValidationException;

/**
 * What a customer can see and do on their Voyar server beyond power and
 * reinstall: backups, firewall, ISO, reverse DNS, WAF, SSH / RDP access,
 * usage graphs and the task list (Voyar API, services/* and vm/*).
 *
 * Every action that names something on the server (a backup, an ISO, an IP
 * address, a WAF rule set, a firewall rule) is checked against that server's
 * own list first, so a customer can only act on what belongs to it.
 */
final class VoyarManage
{
    public const SECTIONS = ['backups', 'firewall', 'iso', 'rdns', 'waf', 'graphs', 'access', 'tasks'];

    public const TIMEFRAMES = ['hour', 'day', 'week', 'month', 'year'];

    public function __construct(private readonly VoyarClient $api, private readonly string $id) {}

    /** @return array<string, mixed> */
    public function section(string $name, array $query = []): array
    {
        $id = rawurlencode($this->id);

        return match ($name) {
            'backups' => ['items' => $this->api->get("/services/backups/{$id}")['data'] ?? []],
            'firewall' => ['items' => $this->api->get("/services/firewall/rules/{$id}")['data'] ?? []],
            'iso' => ['items' => $this->api->get("/services/iso/list/{$id}")['data'] ?? []],
            'rdns' => ['items' => $this->api->get("/services/rdns/list/{$id}")['ipaddresses'] ?? []],
            'waf' => ['items' => $this->api->get("/services/waf/list/{$id}")['bindings'] ?? []],
            'graphs' => $this->graphs((string) ($query['timeframe'] ?? 'hour')),
            'access' => $this->access(),
            'tasks' => ['items' => $this->api->get('/services/tasks/list', ['serviceid' => $this->id, 'limit' => 20])['tasks'] ?? []],
            default => throw new \InvalidArgumentException('Unknown section'),
        };
    }

    /**
     * Runs one action. Throws ValidationException for a bad request and
     * VoyarException when Voyar refuses.
     */
    public function act(string $action, array $in): array
    {
        $id = rawurlencode($this->id);

        switch ($action) {
            case 'backup.create':
                return $this->api->post("/services/backups/create/{$id}");

            case 'backup.restore':
            case 'backup.delete':
                $backup = $this->find($this->section('backups')['items'], 'volid', $in['volid'] ?? null);

                return $this->api->post('/services/backups/'.($action === 'backup.restore' ? 'restore' : 'delete')."/{$id}",
                    ['volid' => $backup['volid'], 'storage' => $backup['storage'] ?? 'backup']);

            case 'firewall.create':
                return $this->api->post("/services/firewall/rules/create/{$id}", $this->rule($in));

            case 'firewall.delete':
            case 'firewall.toggle':
                $rule = $this->find($this->section('firewall')['items'], 'pos', $in['pos'] ?? null);
                if (empty($rule['mutable'])) {
                    throw ValidationException::withMessages(['pos' => __('voyar::voyar.manage.rule_locked')]);
                }
                $pos = (int) $rule['pos'];

                return $action === 'firewall.delete'
                    ? $this->api->post("/services/firewall/rules/delete/{$id}/{$pos}")
                    : $this->api->post("/services/firewall/rules/update/{$id}/{$pos}", ['enable' => empty($rule['enable']) ? 1 : 0]);

            case 'iso.mount':
                $iso = $this->find($this->section('iso')['items'], 'encryptedname', $in['encryptedname'] ?? null);

                return $this->api->post("/services/iso/mount/{$id}", ['encryptedname' => $iso['encryptedname'], 'device' => 'ide2']);

            case 'iso.unmount':
                return $this->api->post("/services/iso/unmount/{$id}", ['device' => 'ide2']);

            case 'rdns.update':
                $ip = $this->find($this->section('rdns')['items'], 'ipaddress', $in['ipaddress'] ?? null);
                $host = strtolower(trim((string) ($in['hostname'] ?? '')));
                if (! preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host)) {
                    throw ValidationException::withMessages(['hostname' => __('voyar::voyar.manage.bad_hostname')]);
                }

                return $this->api->post("/services/rdns/update/{$id}", ['ipaddress' => $ip['ipaddress'], 'hostname' => $host]);

            case 'waf.toggle':
                $binding = $this->find($this->section('waf')['items'], 'bindingid', $in['bindingid'] ?? null);
                $ip = (string) ($in['ipaddress'] ?? '');
                if (! array_key_exists($ip, (array) ($binding['ipaddresses'] ?? []))) {
                    throw ValidationException::withMessages(['ipaddress' => __('voyar::voyar.manage.not_yours')]);
                }

                return $this->api->post("/services/waf/toggle/{$id}", ['bindingid' => $binding['bindingid'], 'ipaddress' => $ip]);

            case 'ssh.enable': return $this->api->post("/services/vm/enable-ssh/{$id}");
            case 'ssh.disable': return $this->api->post("/services/vm/disable-ssh/{$id}");
            case 'sshpw.enable': return $this->api->post("/services/vm/enable-ssh-password-auth/{$id}");
            case 'sshpw.disable': return $this->api->post("/services/vm/disable-ssh-password-auth/{$id}");
            case 'rdp.enable': return $this->api->post("/services/vm/enable-rdp/{$id}");
            case 'rdp.disable': return $this->api->post("/services/vm/disable-rdp/{$id}");
            case 'ssh.port':
            case 'rdp.port':
                $port = $this->port($in['port'] ?? null);

                return $this->api->post('/services/vm/'.($action === 'ssh.port' ? 'change-ssh-port' : 'change-rdp-port')."/{$id}", ['port' => $port]);
        }

        throw ValidationException::withMessages(['action' => 'Unknown action']);
    }

    /** CPU and memory over a time frame, as points the page can draw. */
    private function graphs(string $timeframe): array
    {
        $timeframe = in_array($timeframe, self::TIMEFRAMES, true) ? $timeframe : 'hour';
        $data = $this->api->get('/services/vm/graphs/'.rawurlencode($this->id), ['graphs' => 'cpu,memory,network', 'timeframes' => $timeframe])['data'][$timeframe] ?? [];

        // Two shapes are in use: the documented one (cpu as a ratio, mem and
        // maxmem in bytes) and what the API sends today (cpu.usage_percent,
        // memory.usage_percent, network.in / out).
        $points = [];
        foreach ((array) ($data['raw'] ?? []) as $row) {
            $cpu = is_array($row['cpu'] ?? null) ? (float) ($row['cpu']['usage_percent'] ?? ($row['cpu']['usage_ratio'] ?? 0) * 100) : (float) ($row['cpu'] ?? 0) * 100;
            $mem = is_array($row['memory'] ?? null)
                ? (isset($row['memory']['usage_percent']) ? (float) $row['memory']['usage_percent'] : null)
                : (! empty($row['maxmem']) ? (float) ($row['mem'] ?? 0) / (float) $row['maxmem'] * 100 : null);
            $points[] = [
                't' => (int) ($row['time'] ?? 0),
                'cpu' => round($cpu, 1),
                'mem' => $mem === null ? null : round($mem, 1),
                'netin' => isset($row['network']['in']) ? (float) $row['network']['in'] : (isset($row['netin']) ? (float) $row['netin'] : null),
                'netout' => isset($row['network']['out']) ? (float) $row['network']['out'] : (isset($row['netout']) ? (float) $row['netout'] : null),
            ];
        }

        return ['timeframe' => $timeframe, 'points' => $points];
    }

    /** SSH or RDP: whatever the server answers for. */
    private function access(): array
    {
        $id = rawurlencode($this->id);
        $out = [];
        foreach (['ssh' => "/services/vm/get-ssh-status/{$id}", 'rdp' => "/services/vm/get-rdp-port/{$id}", 'sshpw' => "/services/vm/get-ssh-password-auth/{$id}"] as $key => $path) {
            try {
                $d = $this->api->get($path)['data'] ?? [];
                $out[$key] = $d['ssh'] ?? $d['rdp'] ?? $d['ssh_password_auth'] ?? null;
            } catch (VoyarException) {
                $out[$key] = null;   // a Windows server has no SSH, a Linux one no RDP
            }
        }

        return $out;
    }

    /** @return array<string, mixed> the item of $items whose $key equals $value */
    private function find(array $items, string $key, mixed $value): array
    {
        foreach ($items as $item) {
            if ($value !== null && (string) ($item[$key] ?? '') === (string) $value) {
                return $item;
            }
        }

        throw ValidationException::withMessages([$key => __('voyar::voyar.manage.not_yours')]);
    }

    private function port(mixed $value): int
    {
        $port = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($port === false) {
            throw ValidationException::withMessages(['port' => __('voyar::voyar.manage.bad_port')]);
        }

        return $port;
    }

    /** A firewall rule from the form, in Voyar's fields. */
    private function rule(array $in): array
    {
        $type = in_array($in['type'] ?? '', ['in', 'out'], true) ? $in['type'] : null;
        $action = in_array($in['action'] ?? '', ['ACCEPT', 'DROP', 'REJECT'], true) ? $in['action'] : null;
        $proto = (string) ($in['proto'] ?? '');
        $ports = '/^(\d{1,5}(:\d{1,5})?)(,\d{1,5}(:\d{1,5})?)*$/';
        $net = fn ($v) => $v === '' || filter_var(explode('/', $v)[0], FILTER_VALIDATE_IP) && (! str_contains($v, '/') || ctype_digit(explode('/', $v)[1]));

        $rule = [
            'type' => $type, 'action' => $action,
            'proto' => in_array($proto, ['', 'tcp', 'udp', 'icmp'], true) ? $proto : null,
            'dport' => trim((string) ($in['dport'] ?? '')),
            'source' => trim((string) ($in['source'] ?? '')),
            'comment' => mb_substr(trim((string) ($in['comment'] ?? '')), 0, 60),
            'enable' => 1,
        ];
        if ($rule['type'] === null || $rule['action'] === null || $rule['proto'] === null
            || ($rule['dport'] !== '' && ! preg_match($ports, $rule['dport']))
            || ($rule['dport'] !== '' && ! in_array($rule['proto'], ['tcp', 'udp'], true))
            || ! $net($rule['source'])) {
            throw ValidationException::withMessages(['rule' => __('voyar::voyar.manage.bad_rule')]);
        }

        return array_filter($rule, fn ($v) => $v !== '');
    }
}
