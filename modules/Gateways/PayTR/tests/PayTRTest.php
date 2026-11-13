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

// ── Direkt API, stored cards, recurring ────────────────────────────────

function paytrDirect(array $extra = []): void
{
    paytrOn();
    foreach (['integration' => 'direct', 'store_cards' => '1', 'max_installment' => '6'] + $extra as $k => $v) {
        GatewaySettings::updateOrCreate(['gateway' => 'paytr', 'setting' => $k], ['value' => $v]);
    }
}

test('the Direkt API form signs every installment count and posts to PayTR, not here', function () {
    paytrDirect();
    $invoice = paytrInvoice(162.81);

    $form = (new PayTRModule)->directForm($invoice, '203.0.113.9');
    $f = $form['fields'];

    expect($form['success'])->toBeTrue()
        ->and($f['payment_amount'])->toBe('162.81')
        ->and(array_keys($form['tokens']))->toBe([0, 2, 3, 4, 5, 6]);
    foreach ($form['tokens'] as $count => $token) {
        $expected = base64_encode(hash_hmac('sha256', PAYTR_ID.'203.0.113.9'.$f['merchant_oid'].'musteri@example.com'.'162.81'.'card'.$count.'TL'.'1'.'0'.PAYTR_SALT, PAYTR_KEY, true));
        expect($token)->toBe($expected);
    }

    $owner = User::factory()->create();
    $owner->clients()->attach($invoice->client_id);
    $this->actingAs($owner)->get(route('paytr.pay', $invoice))->assertOk()
        ->assertSee('action="'.PayTRModule::DIRECT_URL.'"', false)
        ->assertSee('name="card_number"', false);
});

test('a payment that stored a card keeps it as a PayTR payment method', function () {
    paytrDirect();
    Http::fake(['www.paytr.com/odeme/capi/list' => Http::response([
        ['ctoken' => 'ct-1', 'last_4' => '4358', 'require_cvv' => '0', 'month' => '12', 'year' => '30', 'c_bank' => 'Test Bank', 'c_brand' => 'bonus', 'schema' => 'VISA'],
    ])]);
    $invoice = paytrInvoice();
    $data = paytrCallback('INV'.$invoice->id.'T5', 'success', 16281, 16281) + ['utoken' => 'ut-9'];

    $this->post('/gateway/paytr/callback', $data)->assertOk()->assertSee('OK');

    $card = \App\Models\PaymentMethod::where('client_id', $invoice->client_id)->where('gateway_name', 'paytr')->firstOrFail();
    expect($card->remote_token)->toBe('ct-1')
        ->and($card->gateway_customer_id)->toBe('ut-9')
        ->and($card->last_four)->toBe('4358')
        ->and($card->exp_year)->toBe(2030)
        ->and(\App\Models\GatewayCustomer::idFor('paytr', $invoice->client_id))->toBe('ut-9');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/capi/list')
        && $r->data()['paytr_token'] === base64_encode(hash_hmac('sha256', 'ut-9'.PAYTR_SALT, PAYTR_KEY, true)));
});

test('installments are offered for a credit card of a known family, not for a debit card', function () {
    paytrDirect();
    \Illuminate\Support\Facades\Cache::flush();
    Http::fake([
        'www.paytr.com/odeme/api/bin-detail' => function (Request $r) {
            return $r->data()['bin_number'] === '43550843'
                ? Http::response(['status' => 'success', 'cardType' => 'credit', 'brand' => 'bonus', 'bank' => 'Garanti', 'businessCard' => 'n'])
                : Http::response(['status' => 'success', 'cardType' => 'debit', 'brand' => 'none', 'bank' => 'X']);
        },
        'www.paytr.com/odeme/taksit-oranlari' => Http::response(['status' => 'success', 'oranlar' => ['bonus' => ['taksit_2' => 2.5, 'taksit_3' => 3.9]]]),
    ]);
    $invoice = paytrInvoice(300);
    $owner = User::factory()->create();
    $owner->clients()->attach($invoice->client_id);

    $credit = $this->actingAs($owner)->getJson(route('paytr.bin', $invoice).'?bin=43550843')->assertOk()->json();
    expect($credit['brand'])->toBe('bonus')
        ->and(collect($credit['installments'])->pluck('count')->all())->toBe([2, 3, 4, 5, 6])
        ->and($credit['installments'][1]['rate'])->toBe(3.9);

    $debit = $this->actingAs($owner)->getJson(route('paytr.bin', $invoice).'?bin=55555555')->assertOk()->json();
    expect($debit['installments'])->toBe([]);
});

function paytrStoredCard(Invoice $invoice): \App\Models\PaymentMethod
{
    return \App\Models\PaymentMethod::create([
        'client_id' => $invoice->client_id, 'gateway_name' => 'paytr', 'payment_type' => 'card',
        'remote_token' => 'ct-1', 'gateway_customer_id' => 'ut-9', 'last_four' => '4358', 'status' => \App\Models\PaymentMethod::STATUS_ACTIVE,
    ]);
}

test('a stored card is charged unattended only when recurring is on, and each answer means what it says', function () {
    paytrDirect();
    $invoice = paytrInvoice(100);
    $card = paytrStoredCard($invoice);
    $module = new PayTRModule;

    $answer = 'success';
    Http::fake(['www.paytr.com/odeme' => function () use (&$answer) {
        return Http::response(match ($answer) {
            'declined' => ['status' => 'failed', 'reason' => 'Kart kapali', 'try_again' => false],
            'busy' => ['status' => 'failed', 'reason' => 'Devam eden islem', 'try_again' => true],
            default => ['status' => $answer],
        });
    }]);

    // Off: nothing is sent.
    expect($module->chargeStoredMethod($invoice, $card, 100, ['idempotency_key' => 'k1'])['status'])->toBe('failed');
    Http::assertNothingSent();

    GatewaySettings::updateOrCreate(['gateway' => 'paytr', 'setting' => 'recurring'], ['value' => '1']);

    $ok = $module->chargeStoredMethod($invoice, $card, 100, ['idempotency_key' => 'k2']);
    expect($ok['status'])->toBe('succeeded')->and($ok['transaction_id'])->toStartWith('INV'.$invoice->id.'R');
    Http::assertSent(function (Request $r) use ($ok) {
        $d = $r->data();

        return ($d['recurring_payment'] ?? null) === '1' && $d['non_3d'] === '1' && $d['ctoken'] === 'ct-1' && $d['utoken'] === 'ut-9'
            && $d['merchant_oid'] === $ok['transaction_id']
            && $d['paytr_token'] === base64_encode(hash_hmac('sha256', PAYTR_ID.$d['user_ip'].$d['merchant_oid'].'musteri@example.com'.'100.00'.'card'.'0'.'TL'.'1'.'1'.PAYTR_SALT, PAYTR_KEY, true));
    });

    $answer = 'wait_callback';
    expect($module->chargeStoredMethod($invoice, $card, 100, ['idempotency_key' => 'k3']))->toMatchArray(['success' => false, 'outcome_unknown' => true]);

    $answer = 'busy';
    expect($module->chargeStoredMethod($invoice, $card, 100, ['idempotency_key' => 'k4']))->toMatchArray(['outcome_unknown' => true]);

    $answer = 'declined';
    expect($module->chargeStoredMethod($invoice, $card, 100, ['idempotency_key' => 'k5']))->toMatchArray(['status' => 'failed', 'retryable' => false])
        ->not->toHaveKey('outcome_unknown');
});

test('a replayed charge is asked about by its reference instead of being sent again', function () {
    paytrDirect(['recurring' => '1']);
    $invoice = paytrInvoice(100);
    $card = paytrStoredCard($invoice);
    Http::fake([
        'www.paytr.com/odeme/durum-sorgu' => Http::response(['status' => 'success', 'payment_amount' => '100.00']),
        'www.paytr.com/odeme' => Http::response(['status' => 'success']),
    ]);

    $result = (new PayTRModule)->chargeStoredMethod($invoice, $card, 100, ['idempotency_key' => 'k9', 'replay' => true]);

    expect($result['status'])->toBe('succeeded');
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/durum-sorgu'));
});

test('deleting a stored card deletes it at PayTR with the CAPI signature', function () {
    paytrDirect();
    $invoice = paytrInvoice();
    $card = paytrStoredCard($invoice);
    Http::fake(['www.paytr.com/odeme/capi/delete' => Http::response(['status' => 'success'])]);

    expect((new PayTRModule)->detachStoredMethod($card)['success'])->toBeTrue();
    Http::assertSent(fn (Request $r) => $r->data()['paytr_token'] === base64_encode(hash_hmac('sha256', 'ct-1'.'ut-9'.PAYTR_SALT, PAYTR_KEY, true)));
});

test('the callback of an unattended charge pays its invoice', function () {
    paytrOn();
    $invoice = paytrInvoice(100);
    $oid = 'INV'.$invoice->id.'R'.substr(hash('sha256', 'k'), 0, 32);

    $this->post('/gateway/paytr/callback', paytrCallback($oid, 'success', 10000, 10000))->assertOk();

    expect(strtolower((string) $invoice->fresh()->status))->toBe('paid');
});
