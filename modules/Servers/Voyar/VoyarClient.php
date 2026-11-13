<?php

namespace Modules\Servers\Voyar;

use App\Models\Server;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The Voyar Client API (https://api.voyar.net/client).
 *
 * - Login with the account's e-mail and password returns a token, sent as
 *   X-API-KEY. It lives six hours and is bound to the IP that logged in, so
 *   it is cached per server record and renewed on a 401.
 * - Bodies are form-encoded; nested values use brackets (addons[0][addonid]).
 * - Business errors come back as HTTP 200 with status "failed"; this class
 *   turns every failure into a VoyarException with Voyar's own message.
 */
class VoyarClient
{
    public const DEFAULT_BASE = 'https://api.voyar.net/client';

    /** Renew a little before Voyar's six hours run out. */
    private const TOKEN_TTL = 5 * 3600 + 1800;

    public function __construct(private readonly Server $server) {}

    public static function for(Server $server): self
    {
        return new self($server);
    }

    // ── Catalogue ──────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    public function products(array $filter = []): array
    {
        return $this->get('/resources/products/search', $filter + ['limit' => 200])['products']['data'] ?? [];
    }

    /** @return array<int, array<string, mixed>> families with their operatingsystems */
    public function operatingSystems(): array
    {
        return $this->get('/resources/operatingsystems')['families'] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function locations(): array
    {
        return $this->get('/resources/locations/search', ['limit' => 100])['locations']['data'] ?? [];
    }

    // ── Services ───────────────────────────────────────────────────────

    /** @return array<string, mixed> the whole response; the service is under "service" */
    public function createServer(array $data): array
    {
        return $this->post('/services/services/create', $data);
    }

    /** @return array<string, mixed> the whole response; the password is only here, in connection.password */
    public function createFtp(array $data): array
    {
        return $this->post('/services/ftp/create', $data);
    }

    /** @return array<string, mixed> */
    public function service(string $id): array
    {
        return $this->get('/services/services/get/'.rawurlencode($id))['service'] ?? [];
    }

    public function extend(string $id, int $days): array
    {
        return $this->post('/services/services/extend/'.rawurlencode($id), ['days' => $days]);
    }

    public function upgrade(string $id, string $pid): array
    {
        return $this->post('/services/services/upgrade/'.rawurlencode($id), ['pid' => $pid]);
    }

    public function terminateOptions(string $id): array
    {
        return $this->get('/services/services/terminate-options/'.rawurlencode($id));
    }

    public function terminate(string $id): array
    {
        return $this->post('/services/services/terminate/'.rawurlencode($id));
    }

    public function reinstall(string $id, array $data = []): array
    {
        return $this->post('/services/services/reinstall/'.rawurlencode($id), $data);
    }

    public function changeBillingType(string $id, array $data): array
    {
        return $this->post('/services/services/change-billing-type/'.rawurlencode($id), $data);
    }

    /** @return array<int, array<string, mixed>> */
    public function tasks(string $id): array
    {
        return $this->get('/services/tasks/list', ['serviceid' => $id])['tasks'] ?? [];
    }

    /** Locks an FTP / VPN service (suspension) or unlocks it. */
    public function lock(string $id, bool $locked): array
    {
        return $this->post('/services/services/'.($locked ? 'lock' : 'unlock').'/'.rawurlencode($id));
    }

    // ── Virtual machine ────────────────────────────────────────────────

    public function power(string $id, string $action): array
    {
        if (! in_array($action, ['start', 'stop', 'restart'], true)) {
            throw new VoyarException("Unknown power action {$action}");
        }

        return $this->post('/services/vm/'.$action.'/'.rawurlencode($id));
    }

    /** @return array<string, mixed> state, memory, cpu, uptime */
    public function status(string $id): array
    {
        return $this->get('/services/vm/status/'.rawurlencode($id))['data'] ?? [];
    }

    /** @return array{username?: string, password?: string} */
    public function resetPassword(string $id, ?string $password = null): array
    {
        return $this->post('/services/vm/password/'.rawurlencode($id), array_filter(['password' => $password]))['data'] ?? [];
    }

    /** @return array{sessionToken?: string, expiresIn?: int, password?: string} */
    public function vncSession(string $id): array
    {
        return $this->post('/services/vm/vnc/session/'.rawurlencode($id));
    }

    // ── One-click apps ─────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    public function availableApps(string $id): array
    {
        return $this->get('/services/oneclicks/available/'.rawurlencode($id))['oneclicks'] ?? [];
    }

    public function installApp(string $id, int $appId, array $fields = []): array
    {
        return $this->post('/services/oneclicks/install/'.rawurlencode($id), ['oneclick_id' => $appId, 'fields' => $fields]);
    }

    // ── Plumbing ───────────────────────────────────────────────────────

    /** @return array<string, mixed> the logged-in account (client, permissions) */
    public function account(): array
    {
        return $this->get('/auth/token');
    }

    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, $query);
    }

    public function post(string $path, array $data = []): array
    {
        return $this->send('POST', $path, $data);
    }

    private function send(string $method, string $path, array $data, bool $retried = false): array
    {
        $request = $this->http()->withHeaders(['X-API-KEY' => $this->token()]);

        try {
            $response = $method === 'GET'
                ? $request->get($this->base().$path, $data)
                : $request->asForm()->post($this->base().$path, $data);
        } catch (ConnectionException $e) {
            throw new VoyarException('Voyar API unreachable: '.$e->getMessage());
        }

        if ($response->status() === 401 && ! $retried) {
            Cache::forget($this->cacheKey());

            return $this->send($method, $path, $data, true);
        }

        return $this->unwrap($response->json() ?? [], $response->status());
    }

    private function token(): string
    {
        return Cache::remember($this->cacheKey(), self::TOKEN_TTL, function () {
            $email = trim((string) $this->server->username);
            $password = (string) $this->server->password;
            if ($email === '' || $password === '') {
                throw new VoyarException('Voyar e-mail and password are not set on the server record.');
            }

            try {
                $response = $this->http()->asForm()->post($this->base().'/auth/login', ['email' => $email, 'password' => $password]);
            } catch (ConnectionException $e) {
                throw new VoyarException('Voyar API unreachable: '.$e->getMessage());
            }

            $body = $this->unwrap($response->json() ?? [], $response->status());
            $token = (string) ($body['token']['token'] ?? '');
            if ($token === '') {
                // A trusted-device-less account with 2FA cannot be used by a machine.
                throw new VoyarException(($body['tfa_required'] ?? false)
                    ? 'This Voyar account asks for two-factor authentication; use a sub-user without 2FA for the integration.'
                    : 'Voyar login returned no token.', $body);
            }

            return $token;
        });
    }

    /** @return array<string, mixed> */
    private function unwrap(array $body, int $status): array
    {
        if ($status >= 400 || ($body['status'] ?? null) === 'failed') {
            $message = (string) ($body['message'] ?? $body['error'] ?? "HTTP {$status}");
            throw new VoyarException($message !== '' ? $message : "HTTP {$status}", $body, $status);
        }

        return $body;
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(60)
            ->connectTimeout(10)
            ->withHeaders(['X-Language' => app()->getLocale() === 'tr' ? 'turkish' : 'english']);
    }

    private function base(): string
    {
        $host = trim((string) $this->server->hostname);
        if ($host === '' || $host === 'api.voyar.net') {
            return self::DEFAULT_BASE;
        }

        return rtrim(str_starts_with($host, 'http') ? $host : 'https://'.$host, '/').'/client';
    }

    private function cacheKey(): string
    {
        return 'voyar.token.'.$this->server->id.'.'.md5((string) $this->server->username);
    }
}
