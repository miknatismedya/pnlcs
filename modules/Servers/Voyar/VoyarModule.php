<?php

namespace Modules\Servers\Voyar;

use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Log;
use Modules\Servers\AbstractServerModule;
use Throwable;

/**
 * Voyar Cloud (voyar.net): cloud servers on monthly (prepaid) or hourly
 * (ondemand) billing, FTP storage, and one-click apps.
 *
 * Setup → Servers: type "voyar", username = the Voyar account e-mail,
 * password = its password, hostname empty (api.voyar.net).
 * Product: package = the Voyar plan; os / location / cpu / app / billing as
 * config options or the customer's configurable options (VoyarOptions).
 *
 * Provisioning sets the service status; this module does the remote work and
 * keeps the Voyar id in module_data (voyar_id). PNLCS retries a failed create,
 * so create() first looks for a server it may already have ordered: ordering
 * twice would be paid twice from the Voyar balance.
 */
class VoyarModule extends AbstractServerModule
{
    public function getModuleName(): string
    {
        return 'voyar';
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'username', 'label' => 'Voyar e-mail', 'type' => 'text', 'required' => true],
            ['name' => 'password', 'label' => 'Voyar password', 'type' => 'password', 'required' => true],
        ];
    }

    // ── Setup screens ──────────────────────────────────────────────────

    public function testConnection(Server $server): bool
    {
        try {
            VoyarClient::for($server)->account();

            return true;
        } catch (Throwable $e) {
            Log::warning('Voyar connection test failed', ['server_id' => $server->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** @return array<int, array{id: string, name: string}> */
    public function listPackages(Server $server): array
    {
        return collect(VoyarClient::for($server)->products())
            ->map(fn ($p) => [
                'id' => (string) $p['id'],
                'name' => trim(($p['product_group']['name'] ?? '').' / '.($p['name'] ?? $p['code'] ?? $p['id']), ' /'),
            ])
            ->values()->all();
    }

    // ── Lifecycle ──────────────────────────────────────────────────────

    public function create(Service $service): array
    {
        return $this->run($service, 'create', function (VoyarClient $api) use ($service) {
            $data = $this->getModuleData($service);
            if (! empty($data['voyar_id'])) {
                return $this->buildResult(true, 'Already created at Voyar: '.$data['voyar_id']);
            }

            $opt = VoyarOptions::forService($service);
            if ($opt->pid === '') {
                return $this->buildResult(false, 'The product has no Voyar plan (package) selected.');
            }

            $order = [
                'pid' => $opt->pid,
                'type' => $opt->billing,
            ];
            if (! $opt->isHourly()) {
                $order['days'] = VoyarOptions::days($service->billing_cycle);
            }

            if ($opt->isFtp()) {
                if ($opt->allowed_ips === []) {
                    return $this->buildResult(false, 'FTP needs at least one allowed IP address (product field "allowed_ips").');
                }
                $created = $api->createFtp($order + ['ip_rules' => $opt->allowed_ips]);
                $remote = $created['service'] ?? [];
                $conn = $created['connection'] ?? [];
                $id = (string) ($remote['id'] ?? $created['public_id'] ?? '');
                if ($id === '') {
                    return $this->buildResult(false, 'Voyar did not return a service id.');
                }
                $this->setModuleData($service, ['voyar_id' => $id, 'voyar_kind' => 'ftp', 'ftp_host' => $conn['host'] ?? null, 'ftp_port' => $conn['port'] ?? null]);
                $service->update(['username' => (string) ($conn['username'] ?? ''), 'password' => (string) ($conn['password'] ?? '')]);

                return $this->buildResult(true, 'FTP storage created at Voyar.', ['voyar_id' => $id]);
            }

            if ($opt->os === '' || $opt->location === '') {
                return $this->buildResult(false, 'Choose an operating system and a location for this product.');
            }

            $hostname = $this->hostname($service);

            // A retry after a lost answer: the server may already exist.
            if (! empty($data['voyar_ordered_at']) && ($existing = $this->findByHostname($api, $hostname))) {
                $this->remember($service, $existing, $opt);

                return $this->buildResult(true, 'Found the server ordered earlier: '.$existing['id']);
            }
            $this->setModuleData($service, ['voyar_ordered_at' => now()->toIso8601String(), 'voyar_hostname' => $hostname]);

            $order += [
                'osid' => $opt->os,
                'locationid' => $opt->location,
                'hostname' => $hostname,
            ];
            if ($opt->cpu !== '') {
                $order['processor_family'] = $opt->cpu;
            }

            $created = $api->createServer($order);
            $remote = $created['service'] ?? [];
            if (empty($remote['id'])) {
                return $this->buildResult(false, 'Voyar did not return a service id.');
            }
            $this->remember($service, $remote, $opt);

            $password = (string) ($remote['password'] ?? $remote['root_password'] ?? $created['password'] ?? '');
            $service->update(array_filter([
                'username' => (string) ($remote['username'] ?? 'root'),
                'password' => $password !== '' ? $password : null,
            ], fn ($v) => $v !== null));

            return $this->buildResult(true, 'Server ordered at Voyar.', ['voyar_id' => $remote['id']]);
        });
    }

    public function suspend(Service $service, string $reason = ''): array
    {
        return $this->withId($service, 'suspend', function (VoyarClient $api, string $id) use ($service) {
            if ($this->isFtpService($service)) {
                $api->lock($id, true);
            } elseif (($api->status($id)['state'] ?? '') !== 'stopped') {
                $api->power($id, 'stop');
            }

            return $this->buildResult(true, 'Suspended at Voyar.');
        });
    }

    public function unsuspend(Service $service): array
    {
        return $this->withId($service, 'unsuspend', function (VoyarClient $api, string $id) use ($service) {
            if ($this->isFtpService($service)) {
                $api->lock($id, false);
            } elseif (($api->status($id)['state'] ?? '') !== 'running') {
                $api->power($id, 'start');
            }

            return $this->buildResult(true, 'Unsuspended at Voyar.');
        });
    }

    public function terminate(Service $service): array
    {
        $data = $this->getModuleData($service);
        if (empty($data['voyar_id'])) {
            // Never created at Voyar: nothing to end there.
            return $this->buildResult(true, 'Nothing to terminate at Voyar.');
        }

        return $this->withId($service, 'terminate', function (VoyarClient $api, string $id) {
            $options = $api->terminateOptions($id);
            if (($options['can_terminate'] ?? true) === false) {
                return $this->buildResult(false, (string) ($options['message'] ?? $options['error'] ?? 'Voyar does not allow terminating this service yet.'));
            }
            $api->terminate($id);

            return $this->buildResult(true, 'Terminated at Voyar.');
        });
    }

    /** Called by PNLCS after a renewal invoice is paid: buy the same period at Voyar. */
    public function renew(Service $service): array
    {
        return $this->withId($service, 'renew', function (VoyarClient $api, string $id) use ($service) {
            $opt = VoyarOptions::forService($service);
            if ($opt->isHourly()) {
                return $this->buildResult(true, 'Hourly service: nothing to renew.');
            }

            // Safe to repeat: the paid period is keyed by the service's due date.
            $key = (string) optional($service->next_due_date)->toDateString();
            $data = $this->getModuleData($service);
            if ($key !== '' && ($data['voyar_renewed_for'] ?? null) === $key) {
                return $this->buildResult(true, 'Already renewed for '.$key);
            }

            $result = $api->extend($id, VoyarOptions::days($service->billing_cycle));
            $this->setModuleData($service, ['voyar_renewed_for' => $key, 'voyar_expires_at' => $result['expires_at'] ?? null]);

            return $this->buildResult(true, 'Extended at Voyar until '.($result['expires_at'] ?? '?'));
        });
    }

    public function changePassword(Service $service, string $newPassword): array
    {
        if ($this->isFtpService($service)) {
            return $this->buildResult(false, 'Change the FTP password from the storage panel.');
        }

        return $this->withId($service, 'changePassword', function (VoyarClient $api, string $id) use ($service, $newPassword) {
            $result = $api->resetPassword($id, $newPassword);
            $service->update(['password' => (string) ($result['password'] ?? $newPassword)]);

            return $this->buildResult(true, 'Password changed.');
        });
    }

    public function changePackage(Service $service, array $newPackage): array
    {
        return $this->withId($service, 'changePackage', function (VoyarClient $api, string $id) use ($newPackage) {
            $cfg = is_string($newPackage['config_options'] ?? null)
                ? (json_decode($newPackage['config_options'], true) ?: [])
                : ($newPackage['config_options'] ?? []);
            $pid = (string) ($cfg['package_name'] ?? $cfg['voyar_pid'] ?? '');
            if ($pid === '') {
                return $this->buildResult(false, 'The new product has no Voyar plan.');
            }
            $api->upgrade($id, $pid);

            return $this->buildResult(true, 'Plan changed at Voyar.');
        });
    }

    public function usageUpdate(Server $server): array
    {
        $updated = 0;
        $errors = 0;
        $api = VoyarClient::for($server);

        foreach (Service::where('server_id', $server->id)->whereIn('status', ['active', 'suspended'])->get() as $service) {
            $id = (string) ($this->getModuleData($service)['voyar_id'] ?? '');
            if ($id === '') {
                continue;
            }
            try {
                $remote = $api->service($id);
                $this->setModuleData($service, [
                    'voyar_status' => $remote['status'] ?? null,
                    'voyar_ip' => $this->firstIp($remote),
                    'voyar_expires_at' => $remote['expires_at'] ?? null,
                ]);
                $updated++;
            } catch (Throwable) {
                $errors++;
            }
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    // ── Buttons and panels ─────────────────────────────────────────────

    /** @return array<string, string> */
    public function clientFunctions(?Service $service = null): array
    {
        if ($service && $this->isFtpService($service)) {
            return [];
        }

        return [
            'powerStart' => __('voyar::voyar.action.start'),
            'powerStop' => __('voyar::voyar.action.stop'),
            'powerRestart' => __('voyar::voyar.action.restart'),
        ];
    }

    /** @return array<string, string> */
    public function customFunctions(?Service $service = null): array
    {
        return $this->clientFunctions($service) + ['syncFromVoyar' => __('voyar::voyar.action.sync')];
    }

    public function powerStart(Service $service): array
    {
        return $this->power($service, 'start');
    }

    public function powerStop(Service $service): array
    {
        return $this->power($service, 'stop');
    }

    public function powerRestart(Service $service): array
    {
        return $this->power($service, 'restart');
    }

    public function syncFromVoyar(Service $service): array
    {
        return $this->withId($service, 'sync', function (VoyarClient $api, string $id) use ($service) {
            $remote = $api->service($id);
            $this->remember($service, $remote, VoyarOptions::forService($service));

            return $this->buildResult(true, 'Synced: '.($remote['status'] ?? '?'));
        });
    }

    public function clientAreaPanel(Service $service)
    {
        $ftp = $this->isFtpService($service);
        $families = [];
        $apps = [];
        $error = null;

        // The lists come from Voyar; the page still loads when it is down.
        if (! $ftp && $service->status === 'active' && ($api = $this->api($service)) && ($id = $this->voyarId($service))) {
            try {
                $families = \Modules\Servers\Voyar\Http\ClientController::operatingSystems($api);
                $apps = $api->availableApps($id);
            } catch (VoyarException) {
                $error = __('voyar::voyar.unavailable');
            }
        }

        return view('voyar::client-panel', [
            'service' => $service,
            'data' => $this->getModuleData($service),
            'ftp' => $ftp,
            'families' => $families,
            'apps' => $apps,
            'error' => $error,
        ]);
    }

    public function adminAreaPanel(Service $service)
    {
        return view('voyar::admin-panel', ['service' => $service, 'data' => $this->getModuleData($service)]);
    }

    public function isProvisioned(Service $service): bool
    {
        return ! empty($this->getModuleData($service)['voyar_id']);
    }

    /** @return array<string, string> */
    public function emailMergeFields(Service $service): array
    {
        $data = $this->getModuleData($service);

        return [
            'voyar_ip' => (string) ($data['voyar_ip'] ?? ''),
            'ftp_host' => (string) ($data['ftp_host'] ?? ''),
        ];
    }

    // ── Internals ──────────────────────────────────────────────────────

    /** The Voyar API for this service's server, or null. */
    public function api(Service $service): ?VoyarClient
    {
        $server = $this->getServer($service);

        return $server ? VoyarClient::for($server) : null;
    }

    public function voyarId(Service $service): ?string
    {
        $id = (string) ($this->getModuleData($service)['voyar_id'] ?? '');

        return $id !== '' ? $id : null;
    }

    private function power(Service $service, string $action): array
    {
        return $this->withId($service, 'power-'.$action, function (VoyarClient $api, string $id) use ($action) {
            $result = $api->power($id, $action);

            return $this->buildResult(true, (string) ($result['message'] ?? __('voyar::voyar.done')));
        });
    }

    private function isFtpService(Service $service): bool
    {
        return ($this->getModuleData($service)['voyar_kind'] ?? null) === 'ftp'
            || VoyarOptions::forService($service)->isFtp();
    }

    private function hostname(Service $service): string
    {
        $domain = strtolower(trim((string) $service->domain));
        if ($domain !== '' && preg_match('/^[a-z0-9.-]+$/', $domain)) {
            return $domain;
        }

        return 'viyo-'.$service->id;
    }

    /** @return array<string, mixed>|null */
    private function findByHostname(VoyarClient $api, string $hostname): ?array
    {
        $list = $api->get('/services/services/search', ['search' => $hostname, 'limit' => 20]);
        $rows = $list['services']['data'] ?? $list['services'] ?? [];
        foreach ((array) $rows as $row) {
            if (strcasecmp((string) ($row['hostname'] ?? ''), $hostname) === 0 && ($row['status'] ?? '') !== 'terminated') {
                return $row;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $remote */
    private function remember(Service $service, array $remote, VoyarOptions $opt): void
    {
        $this->setModuleData($service, [
            'voyar_id' => (string) $remote['id'],
            'voyar_kind' => $opt->isFtp() ? 'ftp' : 'vm',
            'voyar_billing' => $remote['type'] ?? $opt->billing,
            'voyar_status' => $remote['status'] ?? null,
            'voyar_ip' => $this->firstIp($remote),
            'voyar_os' => $remote['os']['name'] ?? null,
            'voyar_location' => $remote['location']['name'] ?? null,
            'voyar_expires_at' => $remote['expires_at'] ?? null,
            'voyar_app' => $opt->app !== '' ? $opt->app : null,
        ]);
    }

    private function firstIp(array $remote): ?string
    {
        foreach ($remote['ipaddresses'] ?? [] as $ip) {
            if (($ip['type'] ?? 'ipv4') === 'ipv4' && ! empty($ip['ipaddress'])) {
                return (string) $ip['ipaddress'];
            }
        }

        return null;
    }

    /** Runs an action that needs the Voyar id. */
    private function withId(Service $service, string $action, callable $fn): array
    {
        return $this->run($service, $action, function (VoyarClient $api) use ($service, $fn) {
            $id = $this->voyarId($service);
            if ($id === null) {
                return $this->buildResult(false, 'This service has no Voyar id yet.');
            }

            return $fn($api, $id);
        });
    }

    /** Runs an action against the service's server, turning Voyar errors into results. */
    private function run(Service $service, string $action, callable $fn): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No Voyar server is set up (Setup → Servers).');
        }

        try {
            $result = $fn(VoyarClient::for($server));
        } catch (VoyarException $e) {
            $result = $this->buildResult(false, 'Voyar: '.$e->getMessage());
        }

        $this->logAction($service, $action, $result);

        return $result;
    }
}
