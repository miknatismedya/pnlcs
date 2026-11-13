<?php

namespace Modules\Servers\Voyar;

use App\Models\Currency;

/**
 * Voyar's prices, in the shop's money.
 *
 * Voyar quotes in US dollars and says so nowhere in its API (no currency
 * field on prices or on the account), so the currency is fixed here. A
 * plan's "prepaid" price is per day, its "ondemand" price per hour; a month
 * is 30 days, as Voyar's own panel counts it.
 *
 * Dollars become the shop's default currency at PNLCS's own exchange rate
 * (Setup -> Currencies, refreshed daily by pnlcs:currency-update), so the
 * figures match every other converted price in the shop.
 */
final class VoyarPricing
{
    public const CURRENCY = 'USD';

    public const DAYS_PER_MONTH = 30;

    /** Shop money per one Voyar dollar, or null when PNLCS has no dollar rate. */
    public static function rate(): ?float
    {
        $default = Currency::getDefault();
        if ($default && strtoupper($default->code) === self::CURRENCY) {
            return 1.0;
        }

        $usd = Currency::where('code', self::CURRENCY)->first();
        $rate = $usd ? (float) $usd->rate : 0.0;

        // Rates are stored against the default currency: 1 default = rate USD.
        return $rate > 0 ? 1 / $rate : null;
    }

    public static function toShop(float $usd): ?float
    {
        $rate = self::rate();

        return $rate === null ? null : $usd * $rate;
    }

    /** A plan or add-on's monthly cost in the shop's money: prepaid per day x 30. */
    public static function monthly(array $price): ?float
    {
        $daily = (float) ($price['prepaid'] ?? 0);

        return $daily > 0 ? self::toShop($daily * self::DAYS_PER_MONTH) : null;
    }

    /** The hourly cost in the shop's money. */
    public static function hourly(array $price): ?float
    {
        $hour = (float) ($price['ondemand'] ?? 0);

        return $hour > 0 ? self::toShop($hour) : null;
    }

    /** A cost plus the margin, in percent. */
    public static function withMargin(float $cost, float $marginPercent): float
    {
        return round($cost * (1 + max(0, $marginPercent) / 100), 2);
    }

    public static function usd(float $amount, int $decimals = 4): string
    {
        return '$'.rtrim(rtrim(number_format($amount, $decimals, '.', ''), '0'), '.');
    }
}
