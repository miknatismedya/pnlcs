<?php

namespace Modules\Gateways\PayTR;

use App\Contracts\GatewayModuleInterface;
use App\Contracts\TokenizableGatewayInterface;
use App\Models\Client;
use App\Models\GatewayCustomer;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Cache;
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
 * Or the Direkt API (https://dev.paytr.com/direkt-api): the card form is
 * on our page but posts straight to PayTR, so the card never reaches this
 * server. It adds installments by card family (BIN lookup), storing the
 * card (CAPI: utoken per customer, ctoken per card, kept as PNLCS payment
 * methods) and paying with a stored card. With PayTR's "recurring payment"
 * permission a stored card can also be charged unattended, which is how
 * PNLCS's automatic card payments use it (TokenizableGatewayInterface,
 * the same way the iyzico module does).
 *
 * merchant_oid carries the invoice: "INV{id}T{time}" for a payment made on
 * the page, "INV{id}R{reference}" for an unattended one; letters and digits
 * only, as PayTR requires.
 */
class PayTRModule implements GatewayModuleInterface, TokenizableGatewayInterface
{
    public const NAME = 'paytr';

    private const TOKEN_URL = 'https://www.paytr.com/odeme/api/get-token';

    private const REFUND_URL = 'https://www.paytr.com/odeme/iade';

    public const IFRAME_URL = 'https://www.paytr.com/odeme/guvenli/';

    /** Direkt API: the card form posts here; also stored-card and recurring payments. */
    public const DIRECT_URL = 'https://www.paytr.com/odeme';

    private const CAPI_LIST_URL = 'https://www.paytr.com/odeme/capi/list';

    private const CAPI_DELETE_URL = 'https://www.paytr.com/odeme/capi/delete';

    private const BIN_URL = 'https://www.paytr.com/odeme/api/bin-detail';

    private const RATES_URL = 'https://www.paytr.com/odeme/taksit-oranlari';

    private const STATUS_URL = 'https://www.paytr.com/odeme/durum-sorgu';

    /** PayTR's own test cards (https://dev.paytr.com/direkt-api/test-kart-bilgileri). */
    public const TEST_CARDS = ['4355 0843 5508 4358', '5406 6754 0667 5403', '9792 0303 9444 0796'];

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
            ['name' => 'integration', 'label' => 'Ödeme sayfası', 'type' => 'select', 'default' => 'iframe',
                'options' => ['iframe' => 'iFrame API (PayTR\'nin kendi ödeme formu)', 'direct' => 'Direkt API (kart formu sitede; taksit, kart kaydetme)'],
                'description' => 'Direkt API\'de kart formu sizin sayfanızdadır ama doğrudan PayTR\'ye gönderilir; kart bilgisi sunucunuza gelmez. PayTR\'den Direkt API yetkisi gerekir.'],
            ['name' => 'test_mode', 'label' => 'Test modu', 'type' => 'yesno', 'default' => '1',
                'description' => 'Açıkken PayTR test kartlarıyla ödeme alınır, para çekilmez. Test kartları ödeme sayfasında gösterilir.'],
            ['name' => 'max_installment', 'label' => 'En fazla taksit (0 = PayTR panelindeki ayar)', 'type' => 'select', 'default' => '0',
                'options' => ['0' => '0', '1' => '1 (taksitsiz)', '2' => '2', '3' => '3', '6' => '6', '9' => '9', '12' => '12']],
            ['name' => 'non_3d', 'label' => 'Direkt API: 3D Secure olmadan öde (Non3D)', 'type' => 'yesno', 'default' => '0',
                'description' => 'PayTR\'den Non3D yetkisi gerekir. Kapalıyken her ödeme 3D Secure ile yapılır.'],
            ['name' => 'store_cards', 'label' => 'Direkt API: müşteri kartını kaydedebilsin', 'type' => 'yesno', 'default' => '1',
                'description' => 'Kayıtlı kartlar Müşteri Paneli → Kayıtlı Kartlar\'da görünür, sonraki ödemelerde seçilebilir.'],
            ['name' => 'recurring', 'label' => 'Kayıtlı karttan otomatik tahsilat', 'type' => 'yesno', 'default' => '0',
                'description' => 'Yenileme faturaları kayıtlı karttan otomatik çekilir (pnlcs otomatik kart tahsilatı açıkken). PayTR\'den Non3D ve tekrarlayan ödeme yetkisi gerekir.'],
            ['name' => 'timeout_limit', 'label' => 'Ödeme ekranı süresi (dakika)', 'type' => 'text', 'default' => '30'],
            ['name' => 'callback_hint', 'label' => 'Bildirim URL', 'type' => 'text', 'default' => '',
                'description' => 'PayTR Mağaza Paneli → Destek & Kurulum → Ayarlar → Bildirim URL alanına şunu yazın: '.url('/gateway/paytr/callback')
                    .' · İşlem sorgulama ve taksit oranları: '.url('/admin/paytr/status')],
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

        if (! preg_match('/^INV(\d+)[TR][0-9A-Za-z]+$/', $oid, $m)) {
            return ['success' => true, 'message' => 'Unknown order', 'response' => 'OK'];
        }

        // What we asked for, in the invoice's currency; total_amount also holds
        // the instalment interest the customer paid PayTR.
        $paid = isset($data['payment_amount']) ? ((int) $data['payment_amount']) / 100 : ((int) $total) / 100;

        // A card the customer asked to keep: PayTR answers with the
        // customer's card-store key (utoken); the cards themselves are read
        // from its card list.
        if (! empty($data['utoken']) && ($invoice = Invoice::find((int) $m[1]))) {
            $this->rememberCards((int) $invoice->client_id, (string) $data['utoken']);
        }

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

    // ── Direkt API ─────────────────────────────────────────────────────

    /**
     * Everything the Direkt API card form needs for an invoice: the hidden
     * fields, and a signed token for every installment count offered
     * (installment_count is part of the signature, the card is not).
     *
     * @return array{success: bool, message?: string, fields?: array<string, string>, tokens?: array<int, string>, stored?: \Illuminate\Support\Collection, test?: bool}
     */
    public function directForm(Invoice $invoice, string $userIp): array
    {
        $s = $this->settings();
        if (! $this->configured($s)) {
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
        $oid = 'INV'.$invoice->id.'T'.now()->format('ymdHis').random_int(10, 99);
        $amount = number_format($due, 2, '.', '');
        $email = (string) ($client?->billing_email ?: $client?->email ?: 'musteri@example.com');
        $testMode = $s['test_mode'] ? '1' : '0';
        $non3d = $s['non_3d'] ? '1' : '0';

        $tokens = [];
        foreach ($this->installmentCounts($s) as $count) {
            $tokens[$count] = self::sign($s['merchant_id'].$userIp.$oid.$email.$amount.'card'.$count.$currency.$testMode.$non3d.$s['merchant_salt'], $s['merchant_key']);
        }

        $fields = [
            'merchant_id' => $s['merchant_id'],
            'user_ip' => $userIp,
            'merchant_oid' => $oid,
            'email' => $email,
            'payment_type' => 'card',
            'payment_amount' => $amount,
            'currency' => $currency,
            'test_mode' => $testMode,
            'non_3d' => $non3d,
            'installment_count' => '0',
            'paytr_token' => $tokens[0],
            'merchant_ok_url' => url('/client/invoices/'.$invoice->id.'?payment=success'),
            'merchant_fail_url' => url('/client/invoices/'.$invoice->id.'?payment=failed'),
            'user_name' => mb_substr(trim(($client?->first_name ?? '').' '.($client?->last_name ?? '')) ?: ($client?->company_name ?? '-'), 0, 60),
            'user_address' => mb_substr(trim(implode(' ', array_filter([$client?->address1, $client?->city, $client?->country]))) ?: '-', 0, 400),
            'user_phone' => mb_substr((string) ($client?->phone_number ?: '0000000000'), 0, 20),
            'user_basket' => json_encode([[mb_substr(__('paytr::paytr.basket_item', ['num' => $invoice->invoice_num ?: $invoice->id]), 0, 100), $amount, 1]], JSON_UNESCAPED_UNICODE),
            'debug_on' => $testMode,
            'client_lang' => app()->getLocale() === 'tr' ? 'tr' : 'en',
        ];
        if ($s['store_cards'] && ($utoken = GatewayCustomer::idFor(self::NAME, (int) $invoice->client_id))) {
            $fields['utoken'] = $utoken;
        }

        return [
            'success' => true,
            'fields' => $fields,
            'tokens' => $tokens,
            'stored' => $this->storedCards((int) $invoice->client_id),
            'store_cards' => $s['store_cards'],
            'test' => $s['test_mode'],
        ];
    }

    /** Installment counts offered: 0 is a single payment; 2.. up to the setting (12 when it is "PayTR's own"). */
    public function installmentCounts(?array $s = null): array
    {
        $s ??= $this->settings();
        $max = $s['max_installment'] === '0' ? 12 : (int) $s['max_installment'];

        return array_merge([0], $max >= 2 ? range(2, $max) : []);
    }

    /**
     * What PayTR knows about a card from its first digits (6 or 8): the card
     * family ("brand") picks the installment rates. Only the BIN is sent, never
     * the card number.
     *
     * @return array{success: bool, brand?: string, card_type?: string, bank?: string, business?: bool, schema?: string, message?: string}
     */
    public function binDetail(string $bin): array
    {
        $s = $this->settings();
        $bin = preg_replace('/\D/', '', $bin);
        if (! $this->configured($s) || strlen($bin) < 6) {
            return ['success' => false];
        }
        $bin = substr($bin, 0, 8);

        return Cache::remember('paytr.bin.'.$bin, 86400, function () use ($s, $bin) {
            try {
                $r = Http::asForm()->timeout(10)->post(self::BIN_URL, [
                    'merchant_id' => $s['merchant_id'], 'bin_number' => $bin,
                    'paytr_token' => self::sign($bin.$s['merchant_id'].$s['merchant_salt'], $s['merchant_key']),
                ])->json() ?? [];
            } catch (ConnectionException) {
                return ['success' => false];
            }
            if (($r['status'] ?? '') !== 'success') {
                return ['success' => false, 'message' => (string) ($r['err_msg'] ?? '')];
            }

            return [
                'success' => true,
                'brand' => (string) ($r['brand'] ?? 'none'),
                'card_type' => (string) ($r['cardType'] ?? ''),
                'bank' => (string) ($r['bank'] ?? ''),
                'business' => ($r['businessCard'] ?? 'n') === 'y',
                'schema' => (string) ($r['schema'] ?? ''),
            ];
        });
    }

    /**
     * PayTR's installment rates per card family, fetched once a day as PayTR
     * asks (rates can change daily).
     *
     * @return array<string, array<int, float>> brand => [count => percent]
     */
    public function installmentRates(): array
    {
        $s = $this->settings();
        if (! $this->configured($s)) {
            return [];
        }

        return Cache::remember('paytr.rates', 86400, function () use ($s) {
            $requestId = (string) time();
            try {
                $r = Http::asForm()->timeout(15)->post(self::RATES_URL, [
                    'merchant_id' => $s['merchant_id'], 'request_id' => $requestId,
                    'paytr_token' => self::sign($s['merchant_id'].$requestId.$s['merchant_salt'], $s['merchant_key']),
                ])->json() ?? [];
            } catch (ConnectionException) {
                return [];
            }

            $out = [];
            foreach ((array) ($r['oranlar'] ?? []) as $brand => $rates) {
                foreach ((array) $rates as $key => $rate) {
                    // "taksit_3" or 3 => percent
                    if (preg_match('/(\d+)$/', (string) $key, $m)) {
                        $out[strtolower((string) $brand)][(int) $m[1]] = (float) $rate;
                    }
                }
            }

            return $out;
        });
    }

    /**
     * A payment as PayTR has it (durum-sorgu).
     *
     * @return array{success: bool, paid?: bool, amount?: float, data?: array, message?: string, not_found?: bool}
     */
    public function statusQuery(string $merchantOid): array
    {
        $s = $this->settings();
        if (! $this->configured($s)) {
            return ['success' => false, 'message' => 'PayTR is not configured.'];
        }
        try {
            $response = Http::asForm()->timeout(20)->post(self::STATUS_URL, [
                'merchant_id' => $s['merchant_id'], 'merchant_oid' => $merchantOid,
                'paytr_token' => self::sign($s['merchant_id'].$merchantOid.$s['merchant_salt'], $s['merchant_key']),
            ]);
        } catch (ConnectionException $e) {
            return ['success' => false, 'message' => 'PayTR unreachable: '.$e->getMessage()];
        }
        $r = $response->json() ?? [];
        if (($r['status'] ?? '') === 'success') {
            return ['success' => true, 'paid' => true, 'amount' => (float) ($r['payment_amount'] ?? 0), 'data' => $r];
        }

        return [
            'success' => $response->successful() && ($r['status'] ?? '') === 'error',
            'paid' => false,
            'not_found' => (string) ($r['err_no'] ?? '') === '004',
            'message' => (string) ($r['err_msg'] ?? $response->body()),
            'data' => $r,
        ];
    }

    // ── Stored cards (CAPI) ────────────────────────────────────────────

    /** The client's PayTR cards kept as payment methods. */
    public function storedCards(int $clientId): \Illuminate\Support\Collection
    {
        return PaymentMethod::where('client_id', $clientId)->where('gateway_name', self::NAME)
            ->where('status', PaymentMethod::STATUS_ACTIVE)->orderByDesc('is_default')->orderByDesc('id')->get();
    }

    /**
     * Keeps the customer's card-store key and copies their cards from PayTR's
     * card list into payment methods (one per ctoken). A card that cannot be
     * stored does not make the payment any less valid.
     */
    public function rememberCards(int $clientId, string $utoken): void
    {
        try {
            GatewayCustomer::remember(self::NAME, $clientId, $utoken);
            foreach ($this->listCards($utoken) as $card) {
                if (empty($card['ctoken'])) {
                    continue;
                }
                $method = PaymentMethod::updateOrCreate(
                    ['client_id' => $clientId, 'gateway_name' => self::NAME, 'remote_token' => (string) $card['ctoken']],
                    [
                        'description' => trim('PayTR '.($card['c_bank'] ?? '').' '.($card['c_brand'] ?? '')),
                        'payment_type' => 'card',
                        'gateway_customer_id' => $utoken,
                        'last_four' => substr((string) ($card['last_4'] ?? ''), -4) ?: null,
                        'card_brand' => (string) ($card['schema'] ?? $card['c_brand'] ?? '') ?: null,
                        'exp_month' => (int) ($card['month'] ?? 0) ?: null,
                        'exp_year' => ($y = (int) ($card['year'] ?? 0)) ? ($y < 100 ? 2000 + $y : $y) : null,
                        'status' => PaymentMethod::STATUS_ACTIVE,
                    ]
                );
                // The first card a client stores is the one PNLCS charges.
                if (! PaymentMethod::where('client_id', $clientId)->where('is_default', true)->exists()) {
                    $method->update(['is_default' => true]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('PayTR: stored card could not be kept: '.$e->getMessage());
        }
    }

    /** @return array<int, array<string, mixed>> PayTR's card list for a utoken */
    public function listCards(string $utoken): array
    {
        $s = $this->settings();
        $r = Http::asForm()->timeout(15)->post(self::CAPI_LIST_URL, [
            'merchant_id' => $s['merchant_id'], 'utoken' => $utoken,
            'paytr_token' => self::sign($utoken.$s['merchant_salt'], $s['merchant_key']),
        ])->json();
        if (! is_array($r) || ($r['status'] ?? '') === 'error') {
            return [];
        }

        return array_values(array_filter($r, 'is_array'));
    }

    /** Whether a stored card needs its CVV typed again before PayTR charges it on the page. */
    public function requiresCvv(PaymentMethod $method): bool
    {
        foreach ($this->listCards((string) $method->gateway_customer_id) as $card) {
            if (($card['ctoken'] ?? null) === $method->remote_token) {
                return (string) ($card['require_cvv'] ?? '0') === '1';
            }
        }

        return true;
    }

    // ── TokenizableGatewayInterface ────────────────────────────────────

    /** PayTR keeps a card only while it is paying something; there is no card form that stores one on its own. */
    public function beginVaulting(Client $client): array
    {
        return ['success' => false, 'customer_id' => GatewayCustomer::idFor(self::NAME, (int) $client->id), 'message' => __('paytr::paytr.no_card_form')];
    }

    public function confirmVaulting(Client $client, string $sessionId): array
    {
        return ['success' => false, 'message' => __('paytr::paytr.no_card_form')];
    }

    /** Deletes the card at PayTR. A card PayTR no longer has counts as deleted. */
    public function detachStoredMethod(PaymentMethod $method): array
    {
        if (strtolower((string) $method->gateway_name) !== self::NAME) {
            return ['success' => false, 'message' => 'Not a PayTR card.', 'retryable' => false];
        }
        $s = $this->settings();
        if (! $this->configured($s)) {
            return ['success' => false, 'message' => 'PayTR is not configured.', 'retryable' => true];
        }
        $utoken = (string) $method->gateway_customer_id;
        $ctoken = (string) $method->remote_token;
        if ($utoken === '' || $ctoken === '') {
            return ['success' => true];
        }
        try {
            $r = Http::asForm()->timeout(15)->post(self::CAPI_DELETE_URL, [
                'merchant_id' => $s['merchant_id'], 'ctoken' => $ctoken, 'utoken' => $utoken,
                'paytr_token' => self::sign($ctoken.$utoken.$s['merchant_salt'], $s['merchant_key']),
            ])->json() ?? [];
        } catch (ConnectionException $e) {
            return ['success' => false, 'message' => 'PayTR unreachable: '.$e->getMessage(), 'retryable' => true];
        }
        if (($r['status'] ?? '') === 'success') {
            return ['success' => true];
        }
        // Not among the customer's cards any more: nothing left to detach.
        $still = collect($this->listCards($utoken))->contains(fn ($c) => ($c['ctoken'] ?? null) === $ctoken);

        return $still
            ? ['success' => false, 'message' => (string) ($r['err_msg'] ?? 'PayTR refused'), 'retryable' => true]
            : ['success' => true];
    }

    /**
     * Charges a stored card with the customer away (PayTR "tekrarlayan
     * ödeme": non_3d=1, recurring_payment=1, utoken + ctoken).
     *
     * merchant_oid is made from the caller's idempotency key, so a replay is
     * asked about by the same reference (durum-sorgu) rather than sent again.
     * Anything that does not say for certain whether money moved answers
     * outcome_unknown, never a retryable failure.
     */
    public function chargeStoredMethod(Invoice $invoice, PaymentMethod $method, float $amount, array $params = []): array
    {
        $replay = ($params['replay'] ?? false) === true;
        $key = is_string($params['idempotency_key'] ?? null) ? trim($params['idempotency_key']) : '';
        $nothing = fn (string $message, bool $retryable = false) => $replay
            ? ['success' => false, 'status' => 'failed', 'message' => $message, 'outcome_unknown' => true]
            : ['success' => false, 'status' => 'failed', 'message' => $message, 'retryable' => $retryable];
        $unknown = fn (string $message) => ['success' => false, 'status' => 'failed', 'message' => $message, 'outcome_unknown' => true];

        $s = $this->settings();
        if (! $this->configured($s) || ! $s['recurring']) {
            return $nothing(__('paytr::paytr.recurring_off'), true);
        }
        if ((int) $method->client_id !== (int) $invoice->client_id || strtolower((string) $method->gateway_name) !== self::NAME
            || $method->trashed() || $method->status !== PaymentMethod::STATUS_ACTIVE) {
            return $nothing(__('paytr::paytr.card_unavailable'));
        }
        if ($key === '') {
            return $nothing(__('paytr::paytr.no_reference'));
        }
        $currency = self::CURRENCIES[strtoupper((string) ($invoice->source_currency ?: shop_currency_code()))] ?? null;
        if ($currency === null || $amount <= 0) {
            return $nothing(__('paytr::paytr.card_unavailable'));
        }

        $oid = 'INV'.$invoice->id.'R'.substr(hash('sha256', $key), 0, 32);

        if ($replay) {
            $known = $this->statusQuery($oid);
            if ($known['paid'] ?? false) {
                return ['success' => true, 'status' => 'succeeded', 'transaction_id' => $oid, 'amount' => round($amount, 2)];
            }

            return $unknown((string) ($known['message'] ?? 'PayTR has no finished payment for '.$oid));
        }

        $client = $invoice->client;
        $email = (string) ($client?->billing_email ?: $client?->email ?: 'musteri@example.com');
        $userIp = (string) ($params['user_ip'] ?? request()?->ip() ?? '127.0.0.1');
        $price = number_format($amount, 2, '.', '');
        $testMode = $s['test_mode'] ? '1' : '0';

        try {
            $response = Http::asForm()->timeout(40)->post(self::DIRECT_URL, [
                'merchant_id' => $s['merchant_id'],
                'user_ip' => $userIp,
                'merchant_oid' => $oid,
                'email' => $email,
                'payment_type' => 'card',
                'payment_amount' => $price,
                'installment_count' => '0',
                'currency' => $currency,
                'test_mode' => $testMode,
                'non_3d' => '1',
                'utoken' => (string) $method->gateway_customer_id,
                'ctoken' => (string) $method->remote_token,
                'recurring_payment' => '1',
                'paytr_token' => self::sign($s['merchant_id'].$userIp.$oid.$email.$price.'card'.'0'.$currency.$testMode.'1'.$s['merchant_salt'], $s['merchant_key']),
                'merchant_ok_url' => url('/client/invoices/'.$invoice->id),
                'merchant_fail_url' => url('/client/invoices/'.$invoice->id),
                'user_name' => mb_substr(trim(($client?->first_name ?? '').' '.($client?->last_name ?? '')) ?: '-', 0, 60),
                'user_address' => mb_substr((string) ($client?->address1 ?: '-'), 0, 400),
                'user_phone' => mb_substr((string) ($client?->phone_number ?: '0000000000'), 0, 20),
                'user_basket' => json_encode([[mb_substr('Fatura '.($invoice->invoice_num ?: $invoice->id), 0, 100), $price, 1]], JSON_UNESCAPED_UNICODE),
                'client_lang' => 'tr',
            ]);
        } catch (ConnectionException $e) {
            return $unknown('PayTR unreachable: '.$e->getMessage());
        }

        $r = $response->json();
        if (! $response->successful() || ! is_array($r)) {
            return $unknown('PayTR gave no readable answer (HTTP '.$response->status().').');
        }

        return match ($r['status'] ?? '') {
            'success' => ['success' => true, 'status' => 'succeeded', 'transaction_id' => $oid, 'amount' => round($amount, 2)],
            'failed' => ($r['try_again'] ?? null) === false || ($r['try_again'] ?? null) === 'false'
                ? ['success' => false, 'status' => 'failed', 'message' => (string) ($r['reason'] ?? $r['msg'] ?? 'declined'), 'decline_code' => (string) ($r['reason'] ?? ''), 'retryable' => false]
                : $unknown((string) ($r['reason'] ?? $r['msg'] ?? 'PayTR: a payment is still in progress')),
            default => $unknown((string) ($r['reason'] ?? $r['msg'] ?? 'PayTR: waiting for the notification')),
        };
    }

    // ── Helpers ────────────────────────────────────────────────────────

    public static function sign(string $data, string $key): string
    {
        return base64_encode(hash_hmac('sha256', $data, $key, true));
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        $rows = GatewaySettings::where('gateway', self::NAME)->get()->mapWithKeys(fn ($r) => [$r->setting => $r->value]);
        $on = fn (string $key, string $default = '0') => ((string) ($rows[$key] ?? $default)) === '1';

        return [
            'merchant_id' => trim((string) ($rows['merchant_id'] ?? '')),
            'merchant_key' => trim((string) ($rows['merchant_key'] ?? '')),
            'merchant_salt' => trim((string) ($rows['merchant_salt'] ?? '')),
            'integration' => ($rows['integration'] ?? 'iframe') === 'direct' ? 'direct' : 'iframe',
            'test_mode' => $on('test_mode', '1'),
            'non_3d' => $on('non_3d'),
            'store_cards' => $on('store_cards', '1'),
            'recurring' => $on('recurring'),
            'max_installment' => in_array((string) ($rows['max_installment'] ?? '0'), ['0', '1', '2', '3', '6', '9', '12'], true) ? (string) ($rows['max_installment'] ?? '0') : '0',
            'timeout_limit' => (string) ($rows['timeout_limit'] ?? '30'),
        ];
    }

    private function configured(array $s): bool
    {
        return $s['merchant_id'] !== '' && $s['merchant_key'] !== '' && $s['merchant_salt'] !== '';
    }
}
