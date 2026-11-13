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
            'families' => $api->operatingSystems(),
            'locations' => $api->locations(),
            'apps' => $this->apps($api),
        ];
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
     * Builds (or rebuilds) the order options and links them to the products.
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
            $group = ConfigOptionGroup::firstOrCreate(['name' => self::GROUP_NAME], ['description' => __('voyar::voyar.setup.group_description')]);
            $currencyId = (Currency::getDefault() ?? Currency::query()->orderBy('id')->first())?->id;

            // Rebuilt from scratch: the options are the wizard's own.
            foreach (ConfigOption::where('group_id', $group->id)->get() as $old) {
                $subIds = ConfigOptionSub::where('config_id', $old->id)->pluck('id');
                Pricing::where('type', ConfigOptionSub::PRICING_TYPE)->whereIn('rel_id', $subIds)->delete();
                ConfigOptionSub::whereIn('id', $subIds)->delete();
                $old->delete();
            }

            $sort = 0;
            foreach (array_keys($titles) as $key) {
                $values = $choices[$key] ?? [];
                if ($values === []) {
                    continue;
                }
                $option = ConfigOption::create([
                    'group_id' => $group->id,
                    'option_name' => $key.'|'.$titles[$key],
                    'option_type' => 'dropdown',
                    'sort_order' => $sort++,
                ]);
                $subSort = 0;
                foreach ($values as $value => $label) {
                    $sub = ConfigOptionSub::create([
                        'config_id' => $option->id,
                        'option_name' => $value.'|'.mb_substr((string) $label, 0, 80),
                        'sort_order' => $subSort++,
                    ]);
                    if ($currencyId) {
                        Pricing::updateOrCreate(
                            ['type' => ConfigOptionSub::PRICING_TYPE, 'rel_id' => $sub->id, 'currency_id' => $currencyId],
                            ['monthly' => 0, 'quarterly' => 0, 'semiannually' => 0, 'annually' => 0, 'biennially' => 0, 'triennially' => 0],
                        );
                    }
                }
            }

            ConfigOptionLink::where('group_id', $group->id)->whereNotIn('product_id', $productIds)->delete();
            foreach ($productIds as $productId) {
                ConfigOptionLink::firstOrCreate(['group_id' => $group->id, 'product_id' => $productId]);
            }

            return $group;
        });
    }

    /** Our products the wizard offers to tie: the cloud and FTP lines, then any other server product. */
    public static function products(): Collection
    {
        return Product::with('group')->where('retired', false)
            ->whereIn('type', ['vps', 'other', 'hosting'])
            ->orderBy('group_id')->orderBy('sort_order')->get();
    }
}
