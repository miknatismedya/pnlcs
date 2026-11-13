<?php

use App\Models\Client;
use App\Models\Credit;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Modules\Addons\ViyoCloud\Support\HourlyBilling;
use Modules\Addons\ViyoCloud\ViyoCloudServiceProvider;
use Tests\TestCase;

/*
 * Hourly cloud billing against a fake Voyar: the server's state and whether
 * Voyar lets it be terminated are set per test in $hb.
 */

uses(TestCase::class, DatabaseTransactions::class);

function hbFake(array &$hb): void
{
    $hb += ['state' => 'running', 'can_terminate' => true, 'calls' => []];

    Http::fake(function (Request $request) use (&$hb) {
        $path = substr(parse_url($request->url(), PHP_URL_PATH), strlen('/client'));
        $hb['calls'][] = $request->method().' '.$path;

        return match (true) {
            $path === '/auth/login' => Http::response(['status' => 'success', 'token' => ['token' => 't']]),
            $path === '/services/services/create' => Http::response(['status' => 'success', 'service' => ['id' => 'VM-1', 'status' => 'active', 'type' => 'ondemand', 'ipaddresses' => [['ipaddress' => '203.0.113.7', 'type' => 'ipv4']]]]),
            str_starts_with($path, '/services/vm/status/') => Http::response(['status' => 'success', 'data' => ['state' => $hb['state']]]),
            str_starts_with($path, '/services/vm/stop/') => (function () use (&$hb) { $hb['state'] = 'stopped'; return Http::response(['status' => 'success']); })(),
            str_starts_with($path, '/services/vm/start/') => (function () use (&$hb) { $hb['state'] = 'running'; return Http::response(['status' => 'success']); })(),
            str_starts_with($path, '/services/services/terminate-options/') => Http::response(['status' => 'success', 'can_terminate' => $hb['can_terminate'], 'message' => $hb['can_terminate'] ? null : 'En az 24 saat kullanım gerekli.']),
            default => Http::response(['status' => 'success']),
        };
    });
}

function hbProduct(float $rate = 0.255): Product
{
    Server::factory()->create(['type' => 'voyar', 'hostname' => 'api.voyar.net', 'ip_address' => '', 'username' => 'r@example.com', 'password' => 'p', 'access_hash' => '']);

    return Product::factory()->create([
        'group_id' => ProductGroup::factory()->create(['slug' => 'cloud-server'])->id,
        'type' => 'vps', 'server_type' => 'voyar', 'hidden' => false, 'retired' => false,
        'config_options' => ['package_name' => '3', 'os' => '13', 'location' => '1', 'hourly_rate' => $rate, 'cpu' => 2, 'ram' => 2, 'disk' => 40],
    ]);
}

function hbClient(float $credit): Client
{
    return Client::factory()->create(['credit' => $credit]);
}

function hbRow(Service $service): object
{
    return DB::table('viyo_hourly_services')->where('service_id', $service->id)->first();
}

beforeEach(function () {
    $this->app->register(ViyoCloudServiceProvider::class);
    app('router')->getRoutes()->refreshNameLookups();
    Cache::flush();
    Mail::fake();
});

test('starting needs a balance for at least one hour', function () {
    $hb = [];
    hbFake($hb);
    $product = hbProduct();

    expect(fn () => app(HourlyBilling::class)->start(hbClient(0.10), $product, []))->toThrow(RuntimeException::class);
    expect(Service::count())->toBe(0);
});

test('a started server is provisioned ondemand, gets no invoice and pays its first hour', function () {
    $hb = [];
    hbFake($hb);
    $client = hbClient(10);

    $service = app(HourlyBilling::class)->start($client, hbProduct(), ['os' => '13', 'location' => '1']);

    expect($service->status)->toBe('active')
        ->and($service->billing_cycle)->toBe('hourly')
        ->and($service->next_due_date)->toBeNull()
        ->and((float) $service->amount)->toBe(0.0)
        ->and($service->module_data['voyar_id'])->toBe('VM-1');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/services/services/create') && $r['type'] === 'ondemand' && ! isset($r['days']));
    expect($client->invoices()->count())->toBe(0);

    // 0,2550: 0,25 taken, 0,005 waits for the next hour.
    expect((float) $client->fresh()->credit)->toBe(9.75);
    expect((float) hbRow($service)->carry)->toBe(0.005);
});

test('hours are charged once each and fractions of a kurus carry over', function () {
    $hb = [];
    hbFake($hb);
    $client = hbClient(10);
    $billing = app(HourlyBilling::class);
    $service = $billing->start($client, hbProduct(), []);
    $start = CarbonImmutable::parse(hbRow($service)->charged_until);

    $billing->charge($service->fresh(), $start->addMinutes(5));
    $billing->charge($service->fresh(), $start->addMinutes(30));     // same hour: nothing more
    $billing->charge($service->fresh(), $start->addHour()->addMinutes(2));

    // Three hours: 0,25 + 0,26 + 0,25 = 0,76, with 0,005 still waiting.
    expect(DB::table('viyo_hourly_charges')->where('service_id', $service->id)->count())->toBe(3)
        ->and((float) $client->fresh()->credit)->toBe(9.24)
        ->and((float) hbRow($service)->total)->toBe(0.765)
        ->and((float) hbRow($service)->carry)->toBe(0.005);

    // One balance line for the day, not one per hour.
    expect(Credit::where('client_id', $client->id)->count())->toBe(1)
        ->and((float) Credit::where('client_id', $client->id)->value('amount'))->toBe(-0.76);
});

test('a stopped server pays the stopped rate', function () {
    $hb = [];
    hbFake($hb);
    $client = hbClient(10);
    $billing = app(HourlyBilling::class);
    $service = $billing->start($client, hbProduct(1.0), []);
    $hb['state'] = 'stopped';

    $billing->charge($service->fresh(), CarbonImmutable::parse(hbRow($service)->charged_until)->addMinute());

    $last = DB::table('viyo_hourly_charges')->where('service_id', $service->id)->orderByDesc('period_start')->first();
    expect((bool) $last->running)->toBeFalse()->and((float) $last->amount)->toBe(0.5);
});

test('out of balance the server is stopped, and a top-up pays the debt and starts it again', function () {
    $hb = [];
    hbFake($hb);
    $client = hbClient(1.20);
    $billing = app(HourlyBilling::class);
    $service = $billing->start($client, hbProduct(1.0), []);   // 0,20 left
    $hour = CarbonImmutable::parse(hbRow($service)->charged_until);

    $billing->charge($service->fresh(), $hour->addMinute());   // 1,00 due, 0,20 there

    $service->refresh();
    expect($service->status)->toBe('suspended')
        ->and($hb['calls'])->toContain('POST /services/vm/stop/VM-1')
        ->and((float) $client->fresh()->credit)->toBe(0.0)
        ->and((float) hbRow($service)->owed)->toBe(0.8);

    $client->forceFill(['credit' => 20])->save();
    $billing->charge($service->fresh(), $hour->addMinutes(20));

    expect($service->fresh()->status)->toBe('active')
        ->and((float) hbRow($service)->owed)->toBe(0.0)
        ->and((float) $client->fresh()->credit)->toBe(19.2)
        ->and($hb['calls'])->toContain('POST /services/vm/start/VM-1');
});

test('a server left without balance past the grace days is terminated', function () {
    $hb = [];
    hbFake($hb);
    $client = hbClient(1.0);
    $billing = app(HourlyBilling::class);
    $service = $billing->start($client, hbProduct(1.0), []);
    $hour = CarbonImmutable::parse(hbRow($service)->charged_until);
    $billing->charge($service->fresh(), $hour->addMinute());
    expect($service->fresh()->status)->toBe('suspended');

    $billing->charge($service->fresh(), $hour->addDays(4));

    expect($service->fresh()->status)->toBe('terminated')
        ->and(hbRow($service)->ended_at)->not->toBeNull()
        ->and($hb['calls'])->toContain('POST /services/services/terminate/VM-1');
});

test('a deletion Voyar refuses is retried on later runs', function () {
    $hb = ['can_terminate' => false];
    hbFake($hb);
    $client = hbClient(10);
    $billing = app(HourlyBilling::class);
    $service = $billing->start($client, hbProduct(), []);

    expect($billing->destroy($service->fresh())['success'])->toBeFalse();
    expect(hbRow($service)->terminate_requested_at)->not->toBeNull()
        ->and(hbRow($service)->ended_at)->toBeNull();

    $hb['can_terminate'] = true;
    $billing->charge($service->fresh(), CarbonImmutable::parse(hbRow($service)->charged_until)->addMinute());

    expect(hbRow($service)->ended_at)->not->toBeNull()
        ->and($service->fresh()->status)->toBe('terminated');
});

test('the order page only accepts choices the product offers and needs a signed-in client', function () {
    $hb = [];
    hbFake($hb);
    $product = hbProduct();
    $client = hbClient(10);
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    $this->get(route('viyocloud.hourly.create'))->assertRedirect();
    $this->actingAs($user)->get(route('viyocloud.hourly.create', ['plan' => $product->slug]))->assertOk()->assertSee($product->name);

    $this->actingAs($user)->post(route('viyocloud.hourly.store'), ['plan' => $product->slug, 'accept' => '1', 'hostname' => 'bad host!'])
        ->assertSessionHasErrors('hostname');
    $this->actingAs($user)->post(route('viyocloud.hourly.store'), ['plan' => $product->slug, 'accept' => '1', 'hostname' => 'srv01'])
        ->assertRedirect();
    expect(Service::where('client_id', $client->id)->where('billing_cycle', 'hourly')->count())->toBe(1);
});

test('nobody sees or deletes another client\'s hourly server', function () {
    $hb = [];
    hbFake($hb);
    $service = app(HourlyBilling::class)->start(hbClient(10), hbProduct(), []);
    $stranger = User::factory()->create();
    $stranger->clients()->attach(hbClient(5)->id);

    $this->actingAs($stranger)->get(route('viyocloud.hourly.usage', $service))->assertForbidden();
    $this->actingAs($stranger)->post(route('viyocloud.hourly.destroy', $service), ['confirm' => '1'])->assertForbidden();
    expect(hbRow($service)->terminate_requested_at)->toBeNull();
});
