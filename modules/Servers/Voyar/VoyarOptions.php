<?php

namespace Modules\Servers\Voyar;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Service;

/**
 * What to order from Voyar for a service.
 *
 * Three sources, the later winning:
 * 1. The product's config options (Setup → Products → module settings):
 *    package_name (Voyar plan id or code), voyar_kind (vm | ftp), os,
 *    location, cpu (amd | intel), billing (prepaid | ondemand).
 * 2. The customer's configurable options, named "key|Title" with choices
 *    "value|Label" (the PNLCS convention the Proxmox module uses too):
 *    os, location, cpu, app, billing.
 *    Or, for orders placed outside the cart, module_data.order_options.
 *    Voyar add-ons are options named "addon_{Voyar add-on id}|Title": a
 *    quantity (how many) or a tick box (one).
 * 3. Product custom fields named "allowed_ips|...": the FTP IP allow-list.
 */
final class VoyarOptions
{
    public const OPTION_KEYS = ['os', 'location', 'cpu', 'app', 'billing'];

    /** Days Voyar is paid for, per PNLCS billing cycle. */
    public const CYCLE_DAYS = [
        'monthly' => 30,
        'quarterly' => 90,
        'semiannually' => 180,
        'annually' => 365,
        'biennially' => 730,
        'triennially' => 1095,
    ];

    /** @param array<string, mixed> $values */
    private function __construct(public readonly array $values) {}

    public static function forService(Service $service): self
    {
        $product = $service->product;
        $cfg = is_array($product?->config_options)
            ? $product->config_options
            : (json_decode((string) $product?->config_options, true) ?: []);

        $values = [
            'pid' => (string) ($cfg['package_name'] ?? $cfg['voyar_pid'] ?? ''),
            'kind' => strtolower((string) ($cfg['voyar_kind'] ?? 'vm')) === 'ftp' ? 'ftp' : 'vm',
            'os' => (string) ($cfg['os'] ?? ''),
            'location' => (string) ($cfg['location'] ?? ''),
            'cpu' => (string) ($cfg['cpu'] ?? ''),
            'app' => (string) ($cfg['app'] ?? ''),
            'billing' => (string) ($cfg['billing'] ?? 'prepaid'),
        ];

        // Choices made on the order form win; an order made outside the cart
        // (the hourly order page) keeps them in module_data.order_options.
        $own = is_array($service->module_data['order_options'] ?? null) ? $service->module_data['order_options'] : [];
        foreach (self::chosen($service) + $own as $key => $value) {
            if (in_array($key, self::OPTION_KEYS, true) && (string) $value !== '') {
                $values[$key] = (string) $value;
            }
        }

        $values['billing'] = $values['billing'] === 'ondemand' ? 'ondemand' : 'prepaid';
        $values['cpu'] = in_array($values['cpu'], ['amd', 'intel'], true) ? $values['cpu'] : '';
        $values['allowed_ips'] = self::allowedIps($service);
        $values['addons'] = self::addons($service);

        return new self($values);
    }

    public function __get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function isFtp(): bool
    {
        return $this->values['kind'] === 'ftp';
    }

    public function isHourly(): bool
    {
        return $this->values['billing'] === 'ondemand';
    }

    public static function days(?string $cycle): int
    {
        return self::CYCLE_DAYS[strtolower((string) $cycle)] ?? 30;
    }

    /** @return array<string, string> */
    private static function chosen(Service $service): array
    {
        if (! $service->exists) {
            return [];
        }

        $out = [];
        foreach ($service->configOptions()->with('option', 'sub')->get() as $row) {
            $key = strtolower(trim(explode('|', (string) $row->option?->option_name, 2)[0]));
            if (in_array($key, self::OPTION_KEYS, true) && $row->sub) {
                $out[$key] = trim(explode('|', (string) $row->sub->option_name, 2)[0]);
            }
        }

        return $out;
    }

    /**
     * Voyar add-ons chosen on the order form: Voyar add-on id => quantity.
     *
     * @return array<int, int>
     */
    private static function addons(Service $service): array
    {
        if (! $service->exists) {
            return [];
        }

        $out = [];
        foreach ($service->configOptions()->with('option')->get() as $row) {
            $key = strtolower(trim(explode('|', (string) $row->option?->option_name, 2)[0]));
            if (! preg_match('/^addon_(\d+)$/', $key, $m)) {
                continue;
            }
            $qty = $row->option->option_type === 'quantity' ? (int) $row->qty : 1;
            if ($qty > 0) {
                $out[(int) $m[1]] = ($out[(int) $m[1]] ?? 0) + $qty;
            }
        }

        return $out;
    }

    /** @return array<int, string> */
    private static function allowedIps(Service $service): array
    {
        if (! $service->exists || ! $service->product_id) {
            return [];
        }

        $fieldIds = CustomField::productFields((int) $service->product_id)
            ->get()
            ->filter(fn ($f) => strtolower(trim(explode('|', (string) $f->field_name, 2)[0])) === 'allowed_ips')
            ->pluck('id');
        if ($fieldIds->isEmpty()) {
            return [];
        }

        $raw = (string) CustomFieldValue::whereIn('field_id', $fieldIds)->where('rel_id', $service->id)->value('value');

        return collect(preg_split('/[\s,;]+/', $raw))
            ->map(fn ($ip) => trim($ip))
            ->filter(fn ($ip) => filter_var(explode('/', $ip)[0], FILTER_VALIDATE_IP))
            ->unique()->values()->all();
    }
}
