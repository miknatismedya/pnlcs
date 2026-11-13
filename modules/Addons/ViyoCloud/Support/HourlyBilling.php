<?php

namespace Modules\Addons\ViyoCloud\Support;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Services\AddonManager;
use App\Services\ProvisioningService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Addons\ViyoCloud\ViyoCloudModule;
use RuntimeException;
use Throwable;

/**
 * Cloud servers paid by the hour from the client's balance.
 *
 * - Each hour is paid at its start: starting a server takes the first hour,
 *   and every run of charge() takes the hours that have begun since.
 * - A running server pays the plan's rate; a stopped one the stopped rate
 *   (its disk and address stay reserved, and the provider charges for them).
 * - Rates have four decimals, the balance two: what is under a kuruş waits in
 *   `carry`; what the balance could not cover is `owed`.
 * - An hour the balance cannot pay: the server is suspended (stopped). Topped up: the debt is
 *   paid and the server started again. Still unpaid after the grace days: it
 *   is terminated, so it stops costing us money.
 */
class HourlyBilling
{
    public const CYCLE = 'hourly';

    // ── Settings ───────────────────────────────────────────────────────

    /** @return array{enabled: bool, stopped_percent: float, grace_days: int, low_hours: int, min_hours: int} */
    public static function settings(): array
    {
        $cfg = rescue(fn () => app(AddonManager::class)->settings(ViyoCloudModule::NAME), [], false);

        return [
            'enabled' => ($cfg['hourly_enabled'] ?? '1') === '1',
            'stopped_percent' => (float) ($cfg['hourly_stopped_percent'] ?? 50),
            'grace_days' => max(0, (int) ($cfg['hourly_grace_days'] ?? 3)),
            'low_hours' => max(1, (int) ($cfg['hourly_low_balance_hours'] ?? 24)),
            'min_hours' => max(1, (int) ($cfg['hourly_min_start_hours'] ?? 1)),
        ];
    }

    public static function rateFor(Product $product): ?float
    {
        $cfg = is_array($product->config_options) ? $product->config_options : (json_decode((string) $product->config_options, true) ?: []);
        $rate = (float) ($cfg['hourly_rate'] ?? 0);

        return $rate > 0 ? round($rate, 4) : null;
    }

    // ── Starting a server ──────────────────────────────────────────────

    /**
     * Orders an hourly server for the client: no invoice, the first hour is
     * taken from the balance once the server is up.
     *
     * @param  array<string, string>  $options  os, location, cpu, app
     */
    public function start(Client $client, Product $product, array $options, string $hostname = ''): Service
    {
        $s = self::settings();
        $rate = self::rateFor($product);
        if (! $s['enabled'] || $rate === null) {
            throw new RuntimeException(__('viyocloud::site.hourly_order.not_available'));
        }
        $needed = round($rate * $s['min_hours'], 2);
        if ((float) $client->fresh()->credit < $needed) {
            throw new RuntimeException(__('viyocloud::site.hourly_order.need_balance', ['amount' => display_money_fmt($needed)]));
        }

        $service = DB::transaction(function () use ($client, $product, $options, $hostname, $rate, $s) {
            $order = Order::create([
                'order_num' => strtoupper(Str::random(8)),
                'client_id' => $client->id,
                'date' => now()->toDateString(),
                'amount' => 0,
                'payment_method' => 'credit',
                'status' => 'active',
                'ip_address' => request()->ip() ?? '0.0.0.0',
                'notes' => 'Hourly cloud',
            ]);

            $service = Service::create([
                'client_id' => $client->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'domain' => $hostname,
                'payment_method' => 'credit',
                'billing_cycle' => self::CYCLE,
                'first_payment_amount' => 0,
                'amount' => 0,
                'next_due_date' => null,
                'registration_date' => now()->toDateString(),
                'status' => 'pending',
                'auto_renew' => false,
            ]);
            $service->forceFill(['module_data' => ['order_options' => array_filter($options + ['billing' => 'ondemand'])]])->save();

            DB::table('viyo_hourly_services')->insert([
                'service_id' => $service->id,
                'client_id' => $client->id,
                'rate' => $rate,
                'stopped_rate' => round($rate * $s['stopped_percent'] / 100, 4),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $service;
        });

        app(ProvisioningService::class)->createAccount($service);
        $this->charge($service->fresh());

        return $service->fresh();
    }

    /** The customer deletes their server. Charging stops when the provider lets it go. */
    public function destroy(Service $service): array
    {
        $row = $this->row($service);
        if (! $row || $row->ended_at) {
            return ['success' => true, 'message' => ''];
        }

        DB::table('viyo_hourly_services')->where('service_id', $service->id)->update(['terminate_requested_at' => now(), 'updated_at' => now()]);
        $result = app(ProvisioningService::class)->terminateAccount($service, false);
        if ($result['success'] ?? false) {
            $this->end($service);
        }

        return $result;
    }

    // ── The hourly run ─────────────────────────────────────────────────

    /** Charges every hourly server; run by the scheduler. @return array{charged: int, suspended: int, resumed: int, terminated: int} */
    public function chargeAll(?CarbonImmutable $now = null): array
    {
        $totals = ['charged' => 0, 'suspended' => 0, 'resumed' => 0, 'terminated' => 0];

        $ids = DB::table('viyo_hourly_services')->whereNull('ended_at')->pluck('service_id');
        foreach (Service::whereIn('id', $ids)->get() as $service) {
            try {
                foreach ($this->charge($service, $now) as $k => $v) {
                    $totals[$k] += $v;
                }
            } catch (Throwable $e) {
                Log::error('Hourly billing failed for a service', ['service_id' => $service->id, 'error' => $e->getMessage()]);
            }
        }

        return $totals;
    }

    /** @return array{charged: int, suspended: int, resumed: int, terminated: int} */
    public function charge(Service $service, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $out = ['charged' => 0, 'suspended' => 0, 'resumed' => 0, 'terminated' => 0];
        $row = $this->row($service);
        if (! $row || $row->ended_at) {
            return $out;
        }

        if (in_array($service->status, ['terminated', 'cancelled', 'fraud'], true)) {
            $this->end($service);

            return $out;
        }
        // Not up yet: nothing is charged until the server exists.
        if (! in_array($service->status, ['active', 'suspended'], true)) {
            return $out;
        }

        // A deletion the provider refused earlier (minimum hours): try again.
        if ($row->terminate_requested_at) {
            $result = app(ProvisioningService::class)->terminateAccount($service, false);
            if ($result['success'] ?? false) {
                $this->end($service);
                $out['terminated']++;

                return $out;
            }
        }

        $running = $service->status === 'active' && $this->isRunning($service);
        $hourStart = $row->charged_until ? CarbonImmutable::parse($row->charged_until) : $now->startOfHour();
        while ($hourStart->lessThanOrEqualTo($now)) {
            $hourEnd = $hourStart->addHour();
            $amount = (float) ($running ? $row->rate : $row->stopped_rate);
            $this->take($service, $hourStart, $hourEnd, $running, $amount);
            $hourStart = $hourEnd;
            $out['charged']++;
        }

        $row = $this->row($service);
        $client = $service->client()->first();
        $balance = (float) $client->credit;

        // An hour that began could not be paid: stop the server. (Not before:
        // the hour already paid for is the customer's to use; the low-balance
        // mail below warns them a day ahead.)
        if ($service->status === 'active' && (float) $row->owed > 0) {
            $result = app(ProvisioningService::class)->suspendAccount($service, __('viyocloud::site.hourly_order.suspend_reason'), false);
            if ($result['success'] ?? false) {
                DB::table('viyo_hourly_services')->where('service_id', $service->id)->update(['suspended_for_balance_at' => $now, 'updated_at' => now()]);
                $this->mail($client, 'suspended', $service);
                $out['suspended']++;
            }

            return $out;
        }

        // Topped up: pay what is owed and start again.
        if ($service->status === 'suspended' && $row->suspended_for_balance_at) {
            if ($balance >= (float) $row->owed + (float) $row->rate) {
                $this->payOwed($service);
                $result = app(ProvisioningService::class)->unsuspendAccount($service->fresh(), false);
                if ($result['success'] ?? false) {
                    DB::table('viyo_hourly_services')->where('service_id', $service->id)->update(['suspended_for_balance_at' => null, 'low_balance_notified_at' => null, 'updated_at' => now()]);
                    $out['resumed']++;
                }
            } elseif (CarbonImmutable::parse($row->suspended_for_balance_at)->addDays(self::settings()['grace_days'])->lessThanOrEqualTo($now)) {
                $result = app(ProvisioningService::class)->terminateAccount($service, false);
                if ($result['success'] ?? false) {
                    $this->end($service);
                    $this->mail($client, 'terminated', $service);
                    $out['terminated']++;
                }
            }

            return $out;
        }

        // Running low: warn once a day.
        $low = (float) $row->rate * self::settings()['low_hours'];
        if ($service->status === 'active' && $balance < $low
            && (! $row->low_balance_notified_at || CarbonImmutable::parse($row->low_balance_notified_at)->addDay()->lessThanOrEqualTo($now))) {
            DB::table('viyo_hourly_services')->where('service_id', $service->id)->update(['low_balance_notified_at' => $now, 'updated_at' => now()]);
            $this->mail($client, 'low', $service, ['hours' => (int) floor($balance / max(0.0001, (float) $row->rate))]);
        }

        return $out;
    }

    // ── Money ──────────────────────────────────────────────────────────

    /** Records one hour and takes it from the balance. Safe to repeat: an hour is charged once. */
    private function take(Service $service, CarbonImmutable $from, CarbonImmutable $to, bool $running, float $amount): void
    {
        DB::transaction(function () use ($service, $from, $to, $running, $amount) {
            $inserted = DB::table('viyo_hourly_charges')->insertOrIgnore([
                'service_id' => $service->id,
                'client_id' => $service->client_id,
                'period_start' => $from,
                'period_end' => $to,
                'running' => $running,
                'amount' => $amount,
                'created_at' => now(),
            ]);
            if ($inserted === 0) {
                DB::table('viyo_hourly_services')->where('service_id', $service->id)->update(['charged_until' => $to]);

                return;
            }

            $row = DB::table('viyo_hourly_services')->where('service_id', $service->id)->lockForUpdate()->first();
            $client = Client::whereKey($service->client_id)->lockForUpdate()->first();

            $due = (float) $row->carry + $amount;
            $cents = floor(round($due * 100, 6)) / 100;
            $taken = min($cents, max(0, (float) $client->credit));

            if ($taken > 0) {
                $client->forceFill(['credit' => round((float) $client->credit - $taken, 2)])->save();
                $this->ledger($service, -$taken);
            }

            DB::table('viyo_hourly_services')->where('service_id', $service->id)->update([
                'charged_until' => $to,
                'carry' => round($due - $cents, 6),
                'owed' => round((float) $row->owed + ($cents - $taken), 4),
                'total' => round((float) $row->total + $amount, 4),
                'updated_at' => now(),
            ]);
        });
    }

    private function payOwed(Service $service): void
    {
        DB::transaction(function () use ($service) {
            $row = DB::table('viyo_hourly_services')->where('service_id', $service->id)->lockForUpdate()->first();
            $client = Client::whereKey($service->client_id)->lockForUpdate()->first();
            $pay = min(round((float) $row->owed, 2), (float) $client->credit);
            if ($pay <= 0) {
                return;
            }
            $client->forceFill(['credit' => round((float) $client->credit - $pay, 2)])->save();
            $this->ledger($service, -$pay);
            DB::table('viyo_hourly_services')->where('service_id', $service->id)->update(['owed' => max(0, round((float) $row->owed - $pay, 4)), 'updated_at' => now()]);
        });
    }

    /** One balance line per server per day, so the credit history stays readable. */
    private function ledger(Service $service, float $amount): void
    {
        $description = __('viyocloud::site.hourly_order.ledger', ['plan' => $service->product?->name, 'id' => $service->id]);
        $line = Credit::where('client_id', $service->client_id)->whereDate('date', now()->toDateString())->where('description', $description)->first();

        if ($line) {
            $line->update(['amount' => round((float) $line->amount + $amount, 2)]);
        } else {
            Credit::create(['client_id' => $service->client_id, 'date' => now()->toDateString(), 'description' => $description, 'amount' => round($amount, 2)]);
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────

    public function row(Service $service): ?object
    {
        return DB::table('viyo_hourly_services')->where('service_id', $service->id)->first();
    }

    public static function isHourly(Service $service): bool
    {
        return $service->billing_cycle === self::CYCLE
            && DB::table('viyo_hourly_services')->where('service_id', $service->id)->exists();
    }

    private function end(Service $service): void
    {
        DB::table('viyo_hourly_services')->where('service_id', $service->id)->whereNull('ended_at')->update(['ended_at' => now(), 'updated_at' => now()]);
    }

    /** Whether the server is on. When the provider cannot be asked, the last answer stands (on, at first). */
    private function isRunning(Service $service): bool
    {
        $module = app(\App\Services\Module\ModuleRegistry::class)->getServerModule((string) $service->product?->server_type);
        if (! $module || ! method_exists($module, 'api')) {
            return true;
        }

        try {
            $api = $module->api($service);
            $id = $module->voyarId($service);
            if (! $api || ! $id) {
                return true;
            }
            $state = (string) ($api->status($id)['state'] ?? '');
            $data = is_array($service->module_data) ? $service->module_data : [];
            $service->forceFill(['module_data' => array_merge($data, ['last_state' => $state])])->saveQuietly();

            return $state !== 'stopped';
        } catch (Throwable) {
            return ($service->module_data['last_state'] ?? 'running') !== 'stopped';
        }
    }

    private function mail(Client $client, string $kind, Service $service, array $extra = []): void
    {
        $to = $client->billing_email ?: $client->email;
        if (! $to) {
            return;
        }

        $params = $extra + ['plan' => $service->product?->name, 'id' => $service->id, 'balance' => display_money_fmt((float) $client->fresh()->credit), 'url' => url('/client/funds')];

        rescue(fn () => Mail::raw(
            __('viyocloud::site.hourly_mail.'.$kind.'_body', $params, $client->language ?: null),
            fn ($m) => $m->to($to)->subject(__('viyocloud::site.hourly_mail.'.$kind.'_subject', $params, $client->language ?: null))
        ), null, true);
    }
}
