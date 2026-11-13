<?php

namespace Modules\Addons\ViyoCloud\Support;

use App\Models\Currency;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * The plans the site shows, read from ordinary PNLCS products.
 *
 * A product line is a product group with a fixed slug, so the operator manages
 * the plans on the normal product screens. Monthly and hourly cloud are the
 * same plans: the hourly rate is the product's `hourly_rate` config option.
 *
 * Plan specs come from the product's config options:
 * cpu, ram (GB), disk (GB), ipv4, storage (GB, FTP), hourly_rate.
 */
class Catalog
{
    public const GROUPS = [
        'cloud' => 'cloud-server',
        'ftp' => 'ftp-storage',
        'apps' => 'app-hosting',
    ];

    /** Billing cycles shown on the site, with their length in months. */
    public const CYCLES = [
        'monthly' => 1,
        'quarterly' => 3,
        'semiannually' => 6,
        'annually' => 12,
    ];

    /** @return Collection<int, array<string, mixed>> */
    public static function plans(string $line): Collection
    {
        $slug = self::GROUPS[$line] ?? null;
        if ($slug === null) {
            return collect();
        }

        $currencyId = Currency::getDefault()?->id;

        return Product::with('pricing', 'translations', 'group')
            ->whereHas('group', fn ($q) => $q->where('slug', $slug))
            ->where('hidden', false)
            ->where('retired', false)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Product $product) => self::plan($product, $currencyId))
            ->values();
    }

    /** The lowest monthly price of a line, formatted, or null when nothing is priced. */
    public static function startingPrice(string $line): ?string
    {
        $min = self::plans($line)->map(fn ($p) => $p['monthly_raw'])->filter()->min();

        return $min ? display_money_fmt($min) : null;
    }

    /** The lowest hourly rate, formatted, or null. */
    public static function startingHourly(): ?string
    {
        $min = self::plans('cloud')->map(fn ($p) => $p['hourly_raw'])->filter()->min();

        return $min ? self::hourlyFmt($min) : null;
    }

    /** @return array<string, mixed> */
    private static function plan(Product $product, ?int $currencyId): array
    {
        $opts = is_array($product->config_options)
            ? $product->config_options
            : (json_decode((string) $product->config_options, true) ?: []);

        $monthly = $product->priceFor('monthly', $currencyId);
        $prices = [];
        foreach (self::CYCLES as $cycle => $months) {
            $price = $product->priceFor($cycle, $currencyId);
            if ($price === null) {
                continue;
            }
            $perMonth = $price / $months;
            $prices[$cycle] = [
                'total' => display_money_fmt($price),
                'per_month' => display_money_fmt($perMonth),
                'saving' => ($monthly && $months > 1) ? max(0, (int) round((1 - $perMonth / $monthly) * 100)) : 0,
            ];
        }

        $hourly = isset($opts['hourly_rate']) && (float) $opts['hourly_rate'] > 0 ? (float) $opts['hourly_rate'] : null;

        return [
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->localizedDescription(),
            'featured' => (bool) $product->is_featured,
            'cpu' => $opts['cpu'] ?? null,
            'ram' => $opts['ram'] ?? null,
            'disk' => $opts['disk'] ?? null,
            'storage' => $opts['storage'] ?? null,
            'ipv4' => $opts['ipv4'] ?? null,
            'prices' => $prices,
            'monthly_raw' => $monthly,
            'hourly_raw' => $hourly,
            'hourly' => $hourly ? self::hourlyFmt($hourly) : null,
            'hourly_month' => $hourly ? display_money_fmt($hourly * 730) : null,
            'order_url' => url('/client/store/configure/'.$product->slug),
        ];
    }

    /**
     * The choices customers get for one order option (location, os, cpu, app),
     * as labels: read from the configurable options linked to the line's
     * products, so the site lists exactly what the order form offers.
     *
     * @return list<string>
     */
    public static function choices(string $key, string $line = 'cloud'): array
    {
        $slug = self::GROUPS[$line] ?? null;
        if ($slug === null) {
            return [];
        }

        $groupIds = \App\Models\ConfigOptionLink::whereIn('product_id', Product::whereHas('group', fn ($q) => $q->where('slug', $slug))->pluck('id'))
            ->pluck('group_id')->unique();

        return \App\Models\ConfigOption::with('subs')->whereIn('group_id', $groupIds)
            ->where('hidden', false)->get()
            ->filter(fn ($o) => strtolower(explode('|', (string) $o->option_name, 2)[0]) === $key)
            ->flatMap(fn ($o) => $o->subs->where('hidden', false)->map(fn ($s) => $s->displayName()))
            ->unique()->values()->all();
    }

    /** Hourly rates need four decimals: 0,2553₺ is not 0,26₺. */
    public static function hourlyFmt(float $amount): string
    {
        $currency = Currency::getDefault();
        if (! $currency) {
            return number_format($amount, 4);
        }
        [$decimal, $thousands] = Currency::FORMATS[(int) $currency->format] ?? Currency::FORMATS[1];

        return $currency->prefix.number_format($amount, 4, $decimal, $thousands).$currency->suffix;
    }
}
