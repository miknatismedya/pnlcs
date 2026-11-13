<?php

namespace Modules\Servers\Voyar;

use App\Models\ConfigOption;
use App\Models\ConfigOptionGroup;
use App\Models\ConfigOptionLink;
use App\Models\ConfigOptionSub;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The setup wizard's work: read Voyar's catalogue, tie our products to
 * Voyar plans, and build the order options (location, system, processor,
 * app) as ordinary configurable options linked to those products.
 */
class VoyarSetup
{
    /** The one option group the wizard owns; rebuilt in place on every run. */
    public const GROUP_NAME = 'Voyar Cloud';

    public static function server(): ?Server
    {
        return Server::where('type', 'voyar')->where('active', true)->where('disabled', false)->orderBy('id')->first();
    }

    /** @return array{account: array, products: array, families: array, locations: array, apps: array} */
    public function catalogue(Server $server): array
    {
        $api = VoyarClient::for($server);

        return [
            'account' => $api->account(),
            'products' => collect($api->products())->where('kind', '!=', 'vpn')->values()->all(),
            'addons' => $this->addons($api),
            'families' => $api->operatingSystems(),
            'locations' => $api->locations(),
            'apps' => $this->apps($api),
        ];
    }

    /**
     * Voyar's add-ons (extra CPU, RAM, disk, IPs, redundant network...),
     * without their images.
     *
     * @return array<int, array<string, mixed>>
     */
    private function addons(VoyarClient $api): array
    {
        try {
            $rows = $api->get('/resources/addon/search')['addons']['data'] ?? [];
        } catch (VoyarException) {
            return [];
        }

        return array_values(array_map(fn ($a) => array_diff_key($a, ['image' => 1]), $rows));
    }

    /**
     * Apps can only be listed for an existing server; any of ours will do.
     *
     * @return array<int, array<string, mixed>>
     */
    private function apps(VoyarClient $api): array
    {
        $module = new VoyarModule;
        $service = Service::whereHas('product', fn ($q) => $q->where('server_type', 'voyar'))
            ->where('status', 'active')->where('module_data', 'like', '%"voyar_id":"VM-%')->first();
        $id = $service ? $module->voyarId($service) : null;
        if (! $id) {
            return [];
        }

        try {
            return $api->availableApps($id);
        } catch (VoyarException) {
            return [];
        }
    }

    /**
     * The Voyar plan that looks like our product: same vCPU, RAM and disk.
     *
     * @param  array<int, array<string, mixed>>  $voyarProducts
     */
    public static function suggest(Product $product, array $voyarProducts): ?string
    {
        $cfg = is_array($product->config_options) ? $product->config_options : (json_decode((string) $product->config_options, true) ?: []);
        if (! empty($cfg['package_name'])) {
            return (string) $cfg['package_name'];
        }
        $want = [(int) ($cfg['cpu'] ?? 0), (int) ($cfg['ram'] ?? 0), (int) ($cfg['disk'] ?? 0)];
        if ($want === [0, 0, 0]) {
            return null;
        }

        foreach ($voyarProducts as $p) {
            if (self::specs($p) === $want) {
                return (string) $p['id'];
            }
        }

        return null;
    }

    /**
     * [vCPU, RAM GB, disk GB] read from a Voyar plan's feature lines
     * ("2 vCPU", "4 GB RAM", "40 GB NVMe").
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function specs(array $voyarProduct): array
    {
        $cpu = $ram = $disk = 0;
        foreach ((array) ($voyarProduct['features'] ?? []) as $line) {
            $line = strtolower((string) $line);
            if (preg_match('/(\d+)\s*v?cpu|(\d+)\s*(core|çekirdek)/u', $line, $m)) {
                $cpu = (int) ($m[1] ?: $m[2]);
            } elseif (preg_match('/(\d+)\s*gb\s*ram/', $line, $m)) {
                $ram = (int) $m[1];
            } elseif (preg_match('/(\d+)\s*gb\s*(nvme|ssd|disk)/', $line, $m)) {
                $disk = (int) $m[1];
            }
        }

        return [$cpu, $ram, $disk];
    }

    /**
     * Ties products to Voyar plans: module voyar, package = plan id.
     *
     * @param  array<int|string, string>  $mapping  product id => Voyar plan id ('' = leave alone)
     * @param  array<int|string, string>  $kinds  product id => vm | ftp
     * @return int products changed
     */
    public function applyMapping(array $mapping, array $kinds = []): int
    {
        $changed = 0;
        foreach ($mapping as $productId => $pid) {
            $pid = trim((string) $pid);
            $product = Product::find($productId);
            if (! $product || $pid === '') {
                continue;
            }
            $cfg = is_array($product->config_options) ? $product->config_options : (json_decode((string) $product->config_options, true) ?: []);
            $cfg['package_name'] = $pid;
            $cfg['voyar_kind'] = ($kinds[$productId] ?? $cfg['voyar_kind'] ?? 'vm') === 'ftp' ? 'ftp' : 'vm';
            $product->update(['server_type' => 'voyar', 'config_options' => $cfg, 'auto_setup' => $product->auto_setup ?: 'payment']);
            $changed++;
        }

        return $changed;
    }

    /**
     * Adds the chosen order options to the "Voyar Cloud" option group and
     * links it to the products. It only ever adds: an option or a choice
     * already there keeps its id, its wording and its prices as the admin
     * left them on Setup -> Products -> Configurable Options; a choice that
     * is no longer ticked is hidden, not deleted, so orders that chose it
     * still read it.
     *
     * @param  array<string, array<int|string, string>>  $choices  key => [value => label]; keys: location, os, cpu, app
     * @param  array<int, int>  $productIds
     */
    public function buildOptions(array $choices, array $productIds): ConfigOptionGroup
    {
        $titles = [
            'location' => __('voyar::voyar.setup.opt.location'),
            'os' => __('voyar::voyar.setup.opt.os'),
            'cpu' => __('voyar::voyar.setup.opt.cpu'),
            'app' => __('voyar::voyar.setup.opt.app'),
        ];

        return DB::transaction(function () use ($choices, $productIds, $titles) {
            $group = $this->group();

            foreach (array_keys($titles) as $sort => $key) {
                $values = $choices[$key] ?? [];
                $option = $this->option($group, $key);
                if ($values === [] && ! $option) {
                    continue;
                }
                $option ??= ConfigOption::create([
                    'group_id' => $group->id, 'option_name' => $key.'|'.$titles[$key],
                    'option_type' => 'dropdown', 'sort_order' => $sort,
                ]);

                $this->mergeChoices($option, $values);
            }

            $this->link($group, $productIds);

            return $group;
        });
    }

    /**
     * Adds Voyar add-ons as order options: a quantity ("how many extra
     * cores") or, for one that is bought once, a tick box. Priced at Voyar's
     * monthly cost in the shop's money plus the margin; running it again
     * sets the prices again, the wording stays as the admin left it.
     *
     * @param  array<int, array<string, mixed>>  $addons  Voyar add-ons (from the catalogue)
     * @param  array<int, int>  $productIds
     * @return int add-ons priced
     */
    public function addAddons(array $addons, float $marginPercent, array $productIds): int
    {
        if (VoyarPricing::rate() === null) {
            throw new VoyarException(__('voyar::voyar.setup.no_rate'));
        }

        return DB::transaction(function () use ($addons, $marginPercent, $productIds) {
            $group = $this->group();
            $n = 0;
            foreach ($addons as $i => $addon) {
                $monthly = VoyarPricing::monthly((array) ($addon['price'] ?? []));
                if ($monthly === null) {
                    continue;
                }
                $max = (int) ($addon['max_qty_effective'] ?? $addon['max_qty'] ?? 1);
                $key = 'addon_'.$addon['id'];

                $option = $this->option($group, $key) ?? ConfigOption::create([
                    'group_id' => $group->id,
                    'option_name' => $key.'|'.mb_substr((string) $addon['name'], 0, 80),
                    'option_type' => $max > 1 ? 'quantity' : 'checkbox',
                    'qty_minimum' => 0,
                    'qty_maximum' => $max > 1 ? $max : 0,
                    'sort_order' => 100 + $i,
                ]);
                $sub = $option->subs()->orderBy('id')->first() ?? ConfigOptionSub::create([
                    'config_id' => $option->id, 'option_name' => $addon['id'].'|'.mb_substr((string) $addon['name'], 0, 80), 'sort_order' => 0,
                ]);
                $this->price($sub, VoyarPricing::withMargin($monthly, $marginPercent));
                $n++;
            }
            $this->link($group, $productIds);

            return $n;
        });
    }

    /**
     * Creates a product in the FTP line for every Voyar FTP plan that has
     * none yet, priced at Voyar's monthly cost plus the margin, with the
     * "allowed IPs" field the order needs. Existing products are left alone.
     *
     * @param  array<int, array<string, mixed>>  $voyarProducts
     * @return int products created
     */
    public function createFtpProducts(array $voyarProducts, float $marginPercent): int
    {
        if (VoyarPricing::rate() === null) {
            throw new VoyarException(__('voyar::voyar.setup.no_rate'));
        }

        $group = \App\Models\ProductGroup::firstOrCreate(['slug' => 'ftp-storage'], ['name' => __('voyar::voyar.setup.ftp_group'), 'hidden' => false]);
        $currency = Currency::getDefault();
        $taken = Product::where('server_type', 'voyar')->get()
            ->map(fn ($p) => (string) ($p->config_options['package_name'] ?? ''))->filter()->all();

        $n = 0;
        foreach ($voyarProducts as $i => $plan) {
            if (($plan['kind'] ?? '') !== 'ftp' || in_array((string) $plan['id'], $taken, true)) {
                continue;
            }
            $monthly = VoyarPricing::monthly((array) ($plan['price'] ?? []));
            if ($monthly === null || ! $currency) {
                continue;
            }
            $price = VoyarPricing::withMargin($monthly, $marginPercent);
            $months = ['monthly' => 1, 'quarterly' => 3, 'semiannually' => 6, 'annually' => 12];

            $product = app(\App\Services\ProductCreator::class)->create([
                'type' => 'other',
                'group_id' => $group->id,
                'name' => (string) $plan['name'],
                'description' => (string) ($plan['description'] ?? ''),
                'server_type' => 'voyar',
                'auto_setup' => 'payment',
                'sort_order' => $i,
                'pay_type' => 'recurring',
            ], [$currency->id => array_map(fn ($m) => round($price * $m, 2), $months)]);
            $product->update(['config_options' => [
                'package_name' => (string) $plan['id'],
                'voyar_kind' => 'ftp',
                'storage' => (int) ($plan['ftp_limits']['quota_gb'] ?? 0) ?: null,
            ]]);

            \App\Models\CustomField::create([
                'type' => 'product', 'rel_id' => $product->id,
                'field_name' => 'allowed_ips|'.__('voyar::voyar.setup.ftp_ips_field'),
                'field_type' => 'textarea', 'description' => __('voyar::voyar.setup.ftp_ips_help'),
                'required' => true, 'show_on_order' => true, 'sort_order' => 0,
            ]);
            $n++;
        }

        return $n;
    }

    private function group(): ConfigOptionGroup
    {
        $group = ConfigOptionGroup::firstOrCreate(['name' => self::GROUP_NAME], ['description' => __('voyar::voyar.setup.group_description')]);
        $group->update(['description' => __('voyar::voyar.setup.group_description')]);

        return $group;
    }

    private function option(ConfigOptionGroup $group, string $key): ?ConfigOption
    {
        return ConfigOption::where('group_id', $group->id)->get()
            ->first(fn ($o) => strtolower(explode('|', (string) $o->option_name, 2)[0]) === $key);
    }

    /** @param array<int|string, string> $values */
    private function mergeChoices(ConfigOption $option, array $values): void
    {
        $subs = ConfigOptionSub::where('config_id', $option->id)->get()
            ->keyBy(fn ($s) => (string) explode('|', (string) $s->option_name, 2)[0]);
        $sort = (int) $subs->max('sort_order');

        foreach ($values as $value => $label) {
            if ($sub = $subs->get((string) $value)) {
                $sub->hidden && $sub->update(['hidden' => false]);

                continue;
            }
            $sub = ConfigOptionSub::create([
                'config_id' => $option->id,
                'option_name' => $value.'|'.mb_substr((string) $label, 0, 80),
                'sort_order' => ++$sort,
            ]);
            $this->price($sub, 0.0);
        }

        foreach ($subs as $value => $sub) {
            if (! array_key_exists($value, $values) && ! $sub->hidden) {
                $sub->update(['hidden' => true]);
            }
        }
    }

    /** One monthly price; longer cycles are that many months. */
    private function price(ConfigOptionSub $sub, float $monthly): void
    {
        $currencyId = (Currency::getDefault() ?? Currency::query()->orderBy('id')->first())?->id;
        if (! $currencyId) {
            return;
        }
        $months = ['monthly' => 1, 'quarterly' => 3, 'semiannually' => 6, 'annually' => 12, 'biennially' => 24, 'triennially' => 36];
        Pricing::updateOrCreate(
            ['type' => ConfigOptionSub::PRICING_TYPE, 'rel_id' => $sub->id, 'currency_id' => $currencyId],
            array_map(fn ($m) => round($monthly * $m, 2), $months),
        );
    }

    /** Links the group to the products; links made by hand stay. */
    private function link(ConfigOptionGroup $group, array $productIds): void
    {
        foreach ($productIds as $productId) {
            ConfigOptionLink::firstOrCreate(['group_id' => $group->id, 'product_id' => $productId]);
        }
    }

    /** Our products the wizard offers to tie: the cloud and FTP lines, then any other server product. */
    public static function products(): Collection
    {
        return Product::with('group')->where('retired', false)
            ->whereIn('type', ['vps', 'other', 'hosting'])
            ->orderBy('group_id')->orderBy('sort_order')->get();
    }
}
