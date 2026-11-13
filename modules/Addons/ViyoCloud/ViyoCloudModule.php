<?php

namespace Modules\Addons\ViyoCloud;

use App\Contracts\AddonModuleInterface;
use Illuminate\Http\Request;
use Modules\Addons\ViyoCloud\Support\Catalog;

/**
 * Viyo Cloud: the public site (cloud, hourly cloud, FTP storage, apps,
 * infrastructure pages) and its translations. Everything lives in this
 * folder, so a PNLCS update never touches it.
 */
class ViyoCloudModule implements AddonModuleInterface
{
    public const NAME = 'viyocloud';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDisplayName(): string
    {
        return 'Viyo Cloud';
    }

    public function getDescription(): string
    {
        // The addon's words load only while it is on; the list shows it off too.
        return \Illuminate\Support\Facades\Lang::has('viyocloud::site.admin.description')
            ? __('viyocloud::site.admin.description')
            : 'Viyo Cloud: site pages, hourly cloud billing and contact details.';
    }

    public function getVersion(): string
    {
        return '0.3.0';
    }

    public function getAuthor(): string
    {
        return 'Viyo Cloud';
    }

    public function activate(): array
    {
        $this->migrate();

        return ['success' => true, 'message' => 'Viyo Cloud activated'];
    }

    /** The addon's own tables (migrations/); safe to run again. */
    private function migrate(): void
    {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--path' => 'modules/Addons/ViyoCloud/migrations', '--force' => true]);
    }

    public function deactivate(): array
    {
        return ['success' => true, 'message' => 'Viyo Cloud deactivated'];
    }

    public function config(): array
    {
        return [
            ['name' => 'phone', 'label' => __('viyocloud::site.admin.cfg.phone'), 'type' => 'text', 'default' => ''],
            ['name' => 'whatsapp', 'label' => __('viyocloud::site.admin.cfg.whatsapp'), 'type' => 'text', 'default' => ''],
            ['name' => 'email', 'label' => __('viyocloud::site.admin.cfg.email'), 'type' => 'text', 'default' => ''],
            ['name' => 'hourly_enabled', 'label' => __('viyocloud::site.admin.cfg.hourly_enabled'), 'type' => 'checkbox', 'default' => '1'],
            ['name' => 'hourly_stopped_percent', 'label' => __('viyocloud::site.admin.cfg.hourly_stopped_percent'), 'type' => 'text', 'default' => '50'],
            ['name' => 'hourly_min_start_hours', 'label' => __('viyocloud::site.admin.cfg.hourly_min_start_hours'), 'type' => 'text', 'default' => '1'],
            ['name' => 'hourly_low_balance_hours', 'label' => __('viyocloud::site.admin.cfg.hourly_low_balance_hours'), 'type' => 'text', 'default' => '24'],
            ['name' => 'hourly_grace_days', 'label' => __('viyocloud::site.admin.cfg.hourly_grace_days'), 'type' => 'text', 'default' => '3'],
        ];
    }

    public function sidebar(): array
    {
        return [
            ['label' => __('viyocloud::site.admin.menu'), 'url' => '/admin/config/addons/modules/'.self::NAME],
        ];
    }

    public function upgrade(string $fromVersion): array
    {
        $this->migrate();

        return ['success' => true, 'message' => 'Upgraded from '.e($fromVersion)];
    }

    /** The addon's admin page: hourly cloud at a glance, and where the site is edited. */
    public function output(Request $request): string
    {
        return view('viyocloud::admin.index', [
            'servers' => \Illuminate\Support\Facades\DB::table('viyo_hourly_services as h')
                ->join('services as s', 's.id', '=', 'h.service_id')
                ->leftJoin('clients as c', 'c.id', '=', 'h.client_id')
                ->leftJoin('products as p', 'p.id', '=', 's.product_id')
                ->whereNull('h.ended_at')
                ->orderByDesc('h.owed')->orderByDesc('h.created_at')
                ->limit(200)
                ->get(['h.*', 's.domain', 's.status as service_status', 'p.name as plan',
                    'c.first_name', 'c.last_name', 'c.company_name', 'c.credit']),
            'income24' => (float) \Illuminate\Support\Facades\DB::table('viyo_hourly_charges')->where('period_start', '>=', now()->subDay())->sum('amount'),
            'income30' => (float) \Illuminate\Support\Facades\DB::table('viyo_hourly_charges')->where('period_start', '>=', now()->subDays(30))->sum('amount'),
            'settings' => \Modules\Addons\ViyoCloud\Support\HourlyBilling::settings(),
            'lines' => collect(Catalog::GROUPS)->map(fn ($slug, $key) => ['slug' => $slug, 'count' => Catalog::plans($key)->count()]),
        ])->render();
    }
}
