<?php

namespace Modules\Servers\Voyar\Console;

use App\Models\Service;
use Illuminate\Console\Command;
use Modules\Servers\Voyar\VoyarException;
use Modules\Servers\Voyar\VoyarModule;

/**
 * Installs the app a customer picked on the order form.
 *
 * Voyar installs apps on a running server only, and a new server takes a few
 * minutes to come up, so the app cannot be installed by create(). This runs
 * every five minutes, starts the install once the server is running and
 * records the task; it gives up after a day so a broken server is not tried
 * forever.
 */
class InstallPendingApps extends Command
{
    protected $signature = 'voyar:install-apps';

    protected $description = 'Install one-click apps chosen at order on Voyar servers that are now running';

    public function handle(): int
    {
        $module = new VoyarModule;

        $services = Service::whereHas('product', fn ($q) => $q->where('server_type', 'voyar'))
            ->where('status', 'active')
            ->where('module_data', 'like', '%"voyar_app"%')
            ->get();

        foreach ($services as $service) {
            $data = is_array($service->module_data) ? $service->module_data : [];
            $appId = (int) ($data['voyar_app'] ?? 0);
            if ($appId < 1 || ! empty($data['voyar_app_task']) || ! empty($data['voyar_app_gave_up'])) {
                continue;
            }

            $ordered = isset($data['voyar_ordered_at']) ? strtotime((string) $data['voyar_ordered_at']) : null;
            if ($ordered && $ordered < time() - 86400) {
                $service->update(['module_data' => array_merge($data, ['voyar_app_gave_up' => now()->toIso8601String()])]);
                $this->warn("Service {$service->id}: gave up installing app {$appId}");

                continue;
            }

            $api = $module->api($service);
            $id = $module->voyarId($service);
            if (! $api || ! $id) {
                continue;
            }

            try {
                if (($api->status($id)['state'] ?? '') !== 'running') {
                    continue;
                }
                $result = $api->installApp($id, $appId);
                $service->update(['module_data' => array_merge($data, [
                    'voyar_app_task' => $result['data']['task_id'] ?? $result['task']['id'] ?? 'started',
                ])]);
                $this->info("Service {$service->id}: app {$appId} install started");
            } catch (VoyarException $e) {
                $this->warn("Service {$service->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
