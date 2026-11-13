<?php

use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\PayTR\PayTRModule;
use Tests\TestCase;

/*
 * PayTR iFrame API. The signatures are the ones in PayTR's own PHP samples:
 * get-token  base64(hmac_sha256(merchant_id.user_ip.merchant_oid.email.payment_amount.user_basket.no_installment.max_installment.currency.test_mode.salt, key))
 * callback   base64(hmac_sha256(merchant_oid.salt.status.total_amount, key))
 */

uses(TestCase::class, DatabaseTransactions::class);

const PAYTR_ID = '123456';
const PAYTR_KEY = 'test-merchant-key';
const PAYTR_SALT = 'test-merchant-salt';

function paytrOn(): void
{
    // The shop sells in lira, as Viyo Cloud does.
    \App\Models\Currency::query()->update(['is_default' => false]);
    \App\Models\Currency::updateOrCreate(['code' => 'TRY'], ['prefix' => '', 'suffix' => '₺', 'format' => 2, 'rate' => 1, 'is_default' => true]);
    app()->forgetInstance('pnlcs.currency');
    \Illuminate\Support\Facades\Cache::flush();

    foreach (['active' => '1', 'merchant_id' => PAYTR_ID, 'merchant_key' => PAYTR_KEY, 'merchant_salt' => PAYTR_SALT, 'test_mode' => '1', 'max_installment' => '0', 'timeout_limit' => '30'] as $k => $v) {
        GatewaySettings::updateOrCreate(['gateway' => 'paytr', 'setting' => $k], ['value' => $v]);
    }
}

function paytrInvoice(float $total = 162.81): Invoice
{
    $client = Client::factory()->create(['email' => 'musteri@example.com', 'first_name' => 'Ayşe', 'last_name' => 'Yılmaz']);

    return Invoice::factory()->create(['client_id' => $client->id, 'status' => 'Unpaid', 'subtotal' => $total, 'total' => $total, 'credit' => 0, 'tax' => 0]);
}

function paytrCallback(string $oid, string $status, int $total, ?int $paymentAmount = null): array
{
    return array_filter([
        'merchant_oid' => $oid, 'status' => $status, 'total_amount' => (string) $total,
        'payment_amount' => $paymentAmount !== null ? (string) $paymentAmount : null,
        'hash' => base64_encode(hash_hmac('sha256', $oid.PAYTR_SALT.$status.$total, PAYTR_KEY, true)),
        'test_mode' => '1', 'currency' => 'TL',
    ], fn ($v) => $v !== null);
}

test('the token request carries the due amount in kuruş and PayTR\'s signature', function () {
    paytrOn();
    Http::fake(['www.paytr.com/odeme/api/get-token' => Http::response(['status' => 'success', 'token' => 'tok123'])]);
    $invoice = paytrInvoice(162.81);

    $result = (new PayTRModule)->getToken($invoice, '203.0.113.9');

    expect($result['success'])->toBeTrue()->and($result['token'])->toBe('tok123');
    Http::assertSent(function (Request $r) use ($invoice) {
        $d = $r->data();
        $hash = base64_encode(hash_hmac('sha256', PAYTR_ID.'203.0.113.9'.$d['merchant_oid'].'musteri@example.com'.'16281'.$d['user_basket'].'0'.'0'.'TL'.'1'.PAYTR_SALT, PAYTR_KEY, true));

        return $d['payment_amount'] === 16281
            && $d['currency'] === 'TL'
            && preg_match('/^INV'.$invoice->id.'T\d+$/', $d['merchant_oid'])
            && $d['paytr_token'] === $hash
            && json_decode(base64_decode($d['user_basket']), true)[0][1] === '162.81';
    });
});

test('a callback with a wrong signature is refused and pays nothing', function () {
    paytrOn();
    $invoice = paytrInvoice();
    $data = paytrCallback('INV'.$invoice->id.'T1', 'success', 16281);
    $data['hash'] = 'forged';

    $this->post('/gateway/paytr/callback', $data)->assertStatus(400);
    expect($invoice->fresh()->status)->not->toBe('Paid');
});

test('a successful callback pays the invoice once and answers OK', function () {
    paytrOn();
    $invoice = paytrInvoice(162.81);
    $oid = 'INV'.$invoice->id.'T2611140101';
    // 3 instalments: the customer paid PayTR 170,00 with interest; we asked 162,81.
    $data = paytrCallback($oid, 'success', 17000, 16281);

    $this->post('/gateway/paytr/callback', $data)->assertOk()->assertSee('OK');
    $this->post('/gateway/paytr/callback', $data)->assertOk()->assertSee('OK');

    $invoice->refresh();
    expect(strtolower((string) $invoice->status))->toBe('paid')
        ->and($invoice->transactions()->count())->toBe(1)
        ->and((float) $invoice->transactions()->first()->amount_in)->toBe(162.81);
});

test('a failed payment answers OK so PayTR stops, and leaves the invoice unpaid', function () {
    paytrOn();
    $invoice = paytrInvoice();

    $this->post('/gateway/paytr/callback', paytrCallback('INV'.$invoice->id.'T3', 'failed', 16281))->assertOk()->assertSee('OK');
    expect(strtolower((string) $invoice->fresh()->status))->toBe('unpaid');
});

test('only the invoice owner opens the payment page', function () {
    paytrOn();
    Http::fake(['www.paytr.com/odeme/api/get-token' => Http::response(['status' => 'success', 'token' => 'tok-page'])]);
    $invoice = paytrInvoice();
    $owner = User::factory()->create();
    $owner->clients()->attach($invoice->client_id);
    $stranger = User::factory()->create();
    $stranger->clients()->attach(Client::factory()->create()->id);

    $this->actingAs($stranger)->get(route('paytr.pay', $invoice))->assertForbidden();
    $this->actingAs($owner)->get(route('paytr.pay', $invoice))->assertOk()->assertSee(PayTRModule::IFRAME_URL.'tok-page', false);
});
