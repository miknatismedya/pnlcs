<?php

namespace Modules\Gateways\PayTR;

use App\Contracts\GatewayModuleInterface;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PayTR iFrame API (https://dev.paytr.com/iframe-api).
 *
 * 1. getToken(): the invoice's amount and the customer are sent to
 *    /odeme/api/get-token, signed with the merchant key and salt; PayTR
 *    answers with a token for its payment page, shown in an iframe.
 * 2. PayTR posts the result to the Bildirim URL, which is PNLCS's generic
 *    /gateway/paytr/callback. processWebhook() checks PayTR's signature and
 *    answers "OK"; the payment is applied once per merchant_oid.
 *
 * merchant_oid carries the invoice: "INV{id}T{time}", letters and digits
 * only, as PayTR requires.
 */
class PayTRModule implements GatewayModuleInterface
{
    public const NAME = 'paytr';

    private const TOKEN_URL = 'https://www.paytr.com/odeme/api/get-token';

    private const REFUND_URL = 'https://www.paytr.com/odeme/iade';

    public const IFRAME_URL = 'https://www.paytr.com/odeme/guvenli/';

    /** PayTR's own currency codes. */
    private const CURRENCIES = ['TRY' => 'TL', 'TL' => 'TL', 'USD' => 'USD', 'EUR' => 'EUR', 'GBP' => 'GBP', 'RUB' => 'RUB'];

    public function getModuleName(): string
    {
        return 'PayTR';
    }

    public function isTokenised(): bool
    {
        return false;
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'merchant_id', 'label' => 'Mağaza No (merchant_id)', 'type' => 'text', 'required' => true],
            ['name' => 'merchant_key', 'label' => 'Mağaza Parola (merchant_key)', 'type' => 'password', 'required' => true],
            ['name' => 'merchant_salt', 'label' => 'Mağaza Gizli Anahtar (merchant_salt)', 'type' => 'password', 'required' => true],
            ['name' => 'test_mode', 'label' => 'Test modu', 'type' => 'yesno', 'default' => '1',
                'description' => 'Açıkken PayTR test kartlarıyla ödeme alınır, para çekilmez.'],
            ['name' => 'max_installment', 'label' => 'En fazla taksit (0 = PayTR panelindeki ayar)', 'type' => 'select', 'default' => '0',
                'options' => ['0' => '0', '1' => '1 (taksitsiz)', '2' => '2', '3' => '3', '6' => '6', '9' => '9', '12' => '12']],
            ['name' => 'timeout_limit', 'label' => 'Ödeme ekranı süresi (dakika)', 'type' => 'text', 'default' => '30'],
            ['name' => 'callback_hint', 'label' => 'Bildirim URL', 'type' => 'text', 'default' => '',
                'description' => 'PayTR Mağaza Paneli → Destek & Kurulum → Ayarlar → Bildirim URL alanına şunu yazın: '.url('/gateway/paytr/callback')],
        ];
    }

    // ── Payment page ───────────────────────────────────────────────────

    public function getPaymentForm(Invoice $invoice): string
    {
        $url = e(route('paytr.pay', $invoice));
        $label = e(__('paytr::paytr.pay_button', ['amount' => invoice_money_fmt($invoice->amountDue(), $invoice)]));
        $note = e(__('paytr::paytr.pay_note'));

        return <<<HTML
<div class="my-3">
    <p class="mb-2">{$note}</p>
    <a href="{$url}" class="btn btn-primary w-100">{$label}</a>
</div>
HTML;
    }

    /**
     * Asks PayTR for a payment page token for what is due on the invoice.
     *
     * @return array{success: bool, token?: string, message?: string, merchant_oid?: string}
     */
    public function getToken(Invoice $invoice, string $userIp): array
    {
        $s = $this->settings();
        if ($s['merchant_id'] === '' || $s['merchant_key'] === '' || $s['merchant_salt'] === '') {
            return ['success' => false, 'message' => 'PayTR is not configured.'];
        }

        $currency = self::CURRENCIES[strtoupper(shop_currency_code())] ?? null;
        if ($currency === null) {
            return ['success' => false, 'message' => 'PayTR does not take '.shop_currency_code().'.'];
        }

        $due = round($invoice->amountDue(), 2);
        if ($due <= 0) {
            return ['success' => false, 'message' => __('paytr::paytr.nothing_due')];
        }

        $client = $invoice->client;
        $merchantOid = 'INV'.$invoice->id.'T'.now()->format('ymdHis').random_int(10, 99);
        $amount = (int) round($due * 100);
        $basket = base64_encode(json_encode([[
            mb_substr(__('paytr::paytr.basket_item', ['num' => $invoice->invoice_num ?: $invoice->id]), 0, 100),
            number_format($due, 2, '.', ''),
            1,
        ]], JSON_UNESCAPED_UNICODE));
        $noInstallment = $s['max_installment'] === '1' ? '1' : '0';
        $maxInstallment = $s['max_installment'] === '1' ? '0' : $s['max_installment'];
        $testMode = $s['test_mode'] ? '1' : '0';
        $email = (string) ($client?->billing_email ?: $client?->email ?: 'musteri@example.com');

        $hashStr = $s['merchant_id'].$userIp.$merchantOid.$email.$amount.$basket.$noInstallment.$maxInstallment.$currency.$testMode;
        $token = self::sign($hashStr.$s['merchant_salt'], $s['merchant_key']);

        $fields = [
            'merchant_id' => $s['merchant_id'],
            'user_ip' => $userIp,
            'merchant_oid' => $merchantOid,
            'email' => $email,
            'payment_amount' => $amount,
            'paytr_token' => $token,
            'user_basket' => $basket,
            'debug_on' => $s['test_mode'] ? '1' : '0',
            'no_installment' => $noInstallment,
            'max_installment' => $maxInstallment,
            'user_name' => mb_substr(trim(($client?->first_name ?? '').' '.($client?->last_name ?? '')) ?: ($client?->company_name ?? '-'), 0, 60),
            'user_address' => mb_substr(trim(implode(' ', array_filter([$client?->address1, $client?->city, $client?->country]))) ?: '-', 0, 400),
            'user_phone' => mb_substr((string) ($client?->phone_number ?: '0000000000'), 0, 20),
            'merchant_ok_url' => url('/client/invoices/'.$invoice->id.'?payment=success'),
            'merchant_fail_url' => url('/client/invoices/'.$invoice->id.'?payment=failed'),
            'timeout_limit' => (string) max(1, (int) $s['timeout_limit']),
            'currency' => $currency,
            'test_mode' => $testMode,
            'lang' => app()->getLocale() === 'tr' ? 'tr' : 'en',
        ];

        try {
            $response = Http::asForm()->timeout(20)->post(self::TOKEN_URL, $fields);
        } catch (ConnectionException $e) {
            return ['success' => false, 'message' => 'PayTR unreachable: '.$e->getMessage()];
        }

        $body = $response->json() ?? [];
        if (($body['status'] ?? '') !== 'success' || empty($body['token'])) {
            Log::warning('PayTR get-token refused', ['invoice_id' => $invoice->id, 'reason' => $body['reason'] ?? $response->body()]);

            return ['success' => false, 'message' => (string) ($body['reason'] ?? 'PayTR refused the request.')];
        }

        return ['success' => true, 'token' => (string) $body['token'], 'merchant_oid' => $merchantOid];
    }

    // ── Result ─────────────────────────────────────────────────────────

    /**
     * PayTR's Bildirim URL post: merchant_oid, status, total_amount, hash, …
     * PayTR repeats it until it reads "OK", so every answer is "OK" once the
     * signature is right; a wrong signature is refused.
     */
    public function processWebhook(array $data): array
    {
        $s = $this->settings();
        $oid = (string) ($data['merchant_oid'] ?? '');
        $status = (string) ($data['status'] ?? '');
        $total = (string) ($data['total_amount'] ?? '');
        $expected = self::sign($oid.$s['merchant_salt'].$status.$total, $s['merchant_key']);

        if ($s['merchant_key'] === '' || ! hash_equals($expected, (string) ($data['hash'] ?? ''))) {
            Log::warning('PayTR callback with a bad hash', ['merchant_oid' => $oid]);

            return ['success' => false, 'message' => 'Bad hash', 'response' => 'PAYTR notification failed: bad hash', 'http_status' => 400];
        }

        if ($status !== 'success') {
            Log::info('PayTR payment failed', ['merchant_oid' => $oid, 'code' => $data['failed_reason_code'] ?? null, 'reason' => $data['failed_reason_msg'] ?? null]);

            return ['success' => true, 'message' => 'Payment failed', 'response' => 'OK'];
        }

        if (! preg_match('/^INV(\d+)T\d+$/', $oid, $m)) {
            return ['success' => true, 'message' => 'Unknown order', 'response' => 'OK'];
        }

        // What we asked for, in the invoice's currency; total_amount also holds
        // the instalment interest the customer paid PayTR.
        $paid = isset($data['payment_amount']) ? ((int) $data['payment_amount']) / 100 : ((int) $total) / 100;

        return [
            'success' => true,
            'invoice_id' => (int) $m[1],
            'transaction_id' => $oid,
            'amount' => round($paid, 2),
            'gateway' => self::NAME,
            'response' => 'OK',
        ];
    }

    public function capture(Invoice $invoice, float $amount, array $params = []): array
    {
        // Paid by the customer on PayTR's page; nothing to charge from here.
        return ['success' => false, 'message' => 'PayTR payments are made on the payment page.'];
    }

    public function refund(string $transactionId, float $amount): array
    {
        $s = $this->settings();
        $returnAmount = number_format($amount, 2, '.', '');
        $token = self::sign($s['merchant_id'].$transactionId.$returnAmount.$s['merchant_salt'], $s['merchant_key']);

        try {
            $response = Http::asForm()->timeout(30)->post(self::REFUND_URL, [
                'merchant_id' => $s['merchant_id'],
                'merchant_oid' => $transactionId,
                'return_amount' => $returnAmount,
                'paytr_token' => $token,
            ]);
        } catch (ConnectionException $e) {
            return ['success' => false, 'message' => 'PayTR unreachable: '.$e->getMessage()];
        }

        $body = $response->json() ?? [];
        if (($body['status'] ?? '') !== 'success') {
            return ['success' => false, 'message' => 'PayTR refund: '.($body['err_msg'] ?? $body['reason'] ?? $response->body())];
        }

        return ['success' => true, 'transaction_id' => $transactionId, 'refund_id' => $transactionId.'-R'.($body['return_amount'] ?? $returnAmount)];
    }

    // ── Helpers ────────────────────────────────────────────────────────

    public static function sign(string $data, string $key): string
    {
        return base64_encode(hash_hmac('sha256', $data, $key, true));
    }

    /** @return array{merchant_id: string, merchant_key: string, merchant_salt: string, test_mode: bool, max_installment: string, timeout_limit: string} */
    private function settings(): array
    {
        $rows = GatewaySettings::where('gateway', self::NAME)->get()->mapWithKeys(fn ($r) => [$r->setting => $r->value]);

        return [
            'merchant_id' => trim((string) ($rows['merchant_id'] ?? '')),
            'merchant_key' => trim((string) ($rows['merchant_key'] ?? '')),
            'merchant_salt' => trim((string) ($rows['merchant_salt'] ?? '')),
            'test_mode' => ($rows['test_mode'] ?? '1') === '1',
            'max_installment' => in_array((string) ($rows['max_installment'] ?? '0'), ['0', '1', '2', '3', '6', '9', '12'], true) ? (string) ($rows['max_installment'] ?? '0') : '0',
            'timeout_limit' => (string) ($rows['timeout_limit'] ?? '30'),
        ];
    }
}
