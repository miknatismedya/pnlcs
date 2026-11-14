<?php

namespace Modules\Servers\Voyar\Http;

use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Servers\Voyar\VoyarClient;
use Modules\Servers\Voyar\VoyarException;
use Modules\Servers\Voyar\VoyarModule;

/**
 * The customer's actions on their Voyar server that need more than a button:
 * live status, the browser console, reinstall with a chosen system, apps.
 */
class ClientController extends Controller
{
    use ResolvesClient;

    public function status(Service $service): JsonResponse
    {
        [$api, $id] = $this->resolve($service);

        try {
            $status = $api->status($id);
        } catch (VoyarException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 502);
        }

        return response()->json([
            'ok' => true,
            'state' => $status['state'] ?? 'unknown',
            'cpu' => $status['cpu']['raw']['usage_percent'] ?? null,
            'memory' => $status['memory']['raw']['usage_percent'] ?? null,
            'memory_text' => trim(($status['memory']['formatted']['used'] ?? '').' / '.($status['memory']['formatted']['total'] ?? ''), ' /'),
            'uptime' => $status['uptime']['formatted'] ?? null,
        ]);
    }

    public function console(Service $service)
    {
        [$api, $id] = $this->resolve($service);

        try {
            $session = $api->vncSession($id);
        } catch (VoyarException $e) {
            return back()->with('error', $e->getMessage());
        }

        return view('voyar::console', [
            'service' => $service,
            'wsUrl' => 'wss://vnc.voyar.net/ws/vnc/'.rawurlencode((string) ($session['sessionToken'] ?? '')),
            'vncPassword' => (string) ($session['password'] ?? ''),
        ]);
    }

    public function reinstall(Request $request, Service $service): RedirectResponse
    {
        [$api, $id] = $this->resolve($service);
        $data = $request->validate([
            'osid' => ['required', 'string', 'max:50'],
            'confirm' => ['accepted'],
        ]);

        // Only systems Voyar offers; the list is cached for an hour.
        $allowed = collect($this->operatingSystems($api))->flatMap(fn ($f) => collect($f['operatingsystems'] ?? [])->pluck('id')->map(fn ($v) => (string) $v));
        abort_unless($allowed->contains($data['osid']), 422);

        try {
            $api->reinstall($id, ['osid' => $data['osid']]);
        } catch (VoyarException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('voyar::voyar.reinstall_started'));
    }

    public function installApp(Request $request, Service $service): RedirectResponse
    {
        [$api, $id] = $this->resolve($service);
        $data = $request->validate([
            'oneclick_id' => ['required', 'integer'],
            'fields' => ['nullable', 'array'],
            'fields.*' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $available = collect($api->availableApps($id))->firstWhere('id', (int) $data['oneclick_id']);
            abort_unless($available, 422);
            $allowedFields = collect($available['fields'] ?? [])->pluck('field_id')->all();
            $api->installApp($id, (int) $data['oneclick_id'], array_intersect_key($data['fields'] ?? [], array_flip($allowedFields)));
        } catch (VoyarException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('voyar::voyar.app_started', ['app' => $available['name'] ?? '']));
    }

    public function password(Service $service): RedirectResponse
    {
        [$api, $id] = $this->resolve($service);

        try {
            $result = $api->resetPassword($id);
        } catch (VoyarException $e) {
            return back()->with('error', $e->getMessage());
        }
        if (! empty($result['password'])) {
            $service->update(['password' => $result['password']]);
        }

        return back()->with('success', __('voyar::voyar.password_reset'));
    }

    /** @return array<int, array<string, mixed>> */
    public static function operatingSystems(VoyarClient $api): array
    {
        return Cache::remember('voyar.os', 3600, fn () => $api->operatingSystems());
    }

    /** @return array{0: VoyarClient, 1: string} */
    /** One tab of the server page, as JSON (backups, firewall, iso, rdns, waf, graphs, access, tasks). */
    public function section(Request $request, Service $service, string $section): JsonResponse
    {
        abort_unless(in_array($section, \Modules\Servers\Voyar\VoyarManage::SECTIONS, true), 404);
        [$api, $id] = $this->resolve($service);

        try {
            return response()->json(['ok' => true] + (new \Modules\Servers\Voyar\VoyarManage($api, $id))->section($section, $request->query()));
        } catch (VoyarException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 502);
        }
    }

    /** One action from a tab; the answer is a sentence for the customer. */
    public function act(Request $request, Service $service, string $action): JsonResponse
    {
        [$api, $id] = $this->resolve($service);

        try {
            $result = (new \Modules\Servers\Voyar\VoyarManage($api, $id))->act($action, $request->except('_token'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (VoyarException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 502);
        }
        \Illuminate\Support\Facades\Log::info('Voyar: customer action', ['service' => $service->id, 'action' => $action]);

        return response()->json(['ok' => true, 'message' => (string) ($result['message'] ?? __('voyar::voyar.manage.done'))]);
    }

    private function resolve(Service $service): array
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        abort_unless(strtolower((string) $service->product?->server_type) === 'voyar', 404);
        abort_unless($service->status === 'active', 403);

        $module = new VoyarModule;
        $id = $module->voyarId($service);
        $api = $module->api($service);
        abort_unless($id && $api, 404);

        return [$api, $id];
    }
}
