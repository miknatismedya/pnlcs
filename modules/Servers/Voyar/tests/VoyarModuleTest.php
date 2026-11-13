<?php

use App\Models\Client;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Voyar\VoyarModule;
use Tests\TestCase;

/*
 * The Voyar module against a fake Voyar API. Request and answer shapes are the
 * ones in Voyar's API reference (wiki.voyar.net/api, _referans/api).
 */

uses(TestCase::class, DatabaseTransactions::class);

const VOYAR = 'https://api.voyar.net/client';

/**
 * A fake Voyar. $state['calls'] records every request as "METHOD path".
 * Answers can be overridden per path in $state['answers'].
 */
function fakeVoyar(array &$state): void
{
    $state += ['calls' => [], 'bodies' => [], 'answers' => [], 'logins' => 0];

    Http::fake(function (Request $request) use (&$state) {
        $path = substr(parse_url($request->url(), PHP_URL_PATH), strlen('/client'));
        $key = $request->method().' '.$path;
        $state['calls'][] = $key;
        $state['bodies'][$key] = $request->data();

        if (isset($state['answers'][$key])) {
            $answer = $state['answers'][$key];
            if (is_callable($answer)) {
                $answer = $answer($request);
            }

            return Http::response($answer[0], $answer[1] ?? 200);
        }

        if ($key === 'POST /auth/login') {
            $state['logins']++;

            return Http::response(['status' => 'success', 'token' => ['token' => 'tok-'.$state['logins'], 'expired_at' => '2099-01-01 00:00:00']]);
        }
        if ($key === 'POST /services/services/create') {
            return Http::response(['status' => 'success', 'service' => voyarRemote(['hostname' => $request->data()['hostname'] ?? 'x', 'type' => $request->data()['type'] ?? 'prepaid'])]);
        }
        if ($key === 'POST /services/ftp/create') {
            return Http::response(['status' => 'success', 'service' => ['id' => 'FTP-77', 'status' => 'active'], 'connection' => ['host' => 'ftp1.voyar.net', 'port' => 21, 'username' => 'u77', 'password' => 'ftp-secret']]);
        }
        if (str_starts_with($key, 'GET /services/vm/status/')) {
            return Http::response(['status' => 'success', 'data' => ['state' => $state['vm_state'] ?? 'running']]);
        }
        if (str_starts_with($key, 'GET /services/services/terminate-options/')) {
            return Http::response(['status' => 'success', 'can_terminate' => true]);
        }
        if (str_starts_with($key, 'POST /services/services/extend/')) {
            return Http::response(['status' => 'success', 'expires_at' => '2026-12-31 12:00:00', 'charged_amount' => 30]);
        }

        return Http::response(['status' => 'success', 'message' => 'ok']);
    });
}

function voyarRemote(array $overrides = []): array
{
    return array_merge([
        'id' => 'VM-190499', 'pid' => 3, 'hostname' => 'srv01', 'status' => 'active', 'type' => 'prepaid',
        'username' => 'root', 'password' => 'Root-pass-1',
        'ipaddresses' => [['id' => 501, 'ipaddress' => '203.0.113.10', 'type' => 'ipv4']],
        'location' => ['id' => 1, 'name' => 'Istanbul', 'code' => 'ist'],
        'os' => ['name' => 'Ubuntu 24.04'], 'expires_at' => '2026-12-01 12:00:00',
    ], $overrides);
}

function voyarServer(): Server
{
    return Server::factory()->create([
        'type' => 'voyar', 'hostname' => '', 'ip_address' => '', 'username' => 'reseller@example.com', 'password' => 'voyar-pass', 'access_hash' => '',
    ]);
}

function voyarService(Server $server, array $config = [], array $moduleData = [], string $cycle = 'monthly'): Service
{
    $client = Client::factory()->create();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'voyar',
        'config_options' => json_encode($config + ['package_name' => '3', 'os' => '13', 'location' => '1']),
    ]);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'order_id' => Order::factory()->create(['client_id' => $client->id])->id,
        'domain' => '', 'status' => 'pending', 'billing_cycle' => $cycle, 'username' => '', 'password' => '',
    ]);
    $service->forceFill(['module_data' => $moduleData])->save();

    return $service->fresh();
}

beforeEach(fn () => Cache::flush());

test('a monthly server is ordered prepaid for 30 days and remembered', function () {
    $state = [];
    fakeVoyar($state);
    $service = voyarService(voyarServer(), ['cpu' => 'amd']);

    $result = (new VoyarModule)->create($service);

    expect($result['success'])->toBeTrue();
    $body = $state['bodies']['POST /services/services/create'];
    expect($body)->toMatchArray(['pid' => '3', 'osid' => '13', 'locationid' => '1', 'type' => 'prepaid', 'days' => 30, 'processor_family' => 'amd', 'hostname' => 'viyo-'.$service->id]);

    $service->refresh();
    expect($service->module_data['voyar_id'])->toBe('VM-190499')
        ->and($service->module_data['voyar_ip'])->toBe('203.0.113.10')
        ->and($service->username)->toBe('root')
        ->and($service->password)->toBe('Root-pass-1');
});

test('the token is sent as X-API-KEY and reused between calls', function () {
    $state = [];
    fakeVoyar($state);
    $service = voyarService(voyarServer());
    $module = new VoyarModule;
    $module->create($service);
    $module->powerRestart($service->fresh());

    expect($state['logins'])->toBe(1);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/vm/restart/') && $r->header('X-API-KEY') === ['tok-1']);
});

test('an expired token is renewed once on a 401', function () {
    $state = ['answers' => ['POST /services/vm/start/VM-1' => function () use (&$state) {
        return $state['logins'] < 2 ? [['status' => 'failed', 'logout' => true], 401] : [['status' => 'success', 'message' => 'started']];
    }]];
    fakeVoyar($state);
    $service = voyarService(voyarServer(), [], ['voyar_id' => 'VM-1']);

    $result = (new VoyarModule)->powerStart($service);

    expect($result['success'])->toBeTrue()->and($state['logins'])->toBe(2);
});

test('create does not order twice when the service already has a Voyar id', function () {
    $state = [];
    fakeVoyar($state);
    $service = voyarService(voyarServer(), [], ['voyar_id' => 'VM-5']);

    expect((new VoyarModule)->create($service)['success'])->toBeTrue()
        ->and($state['calls'])->not->toContain('POST /services/services/create');
});

test('a retry after a lost answer adopts the server ordered the first time', function () {
    $state = ['answers' => ['GET /services/services/search' => [['status' => 'success', 'services' => ['total' => 1, 'data' => [voyarRemote(['id' => 'VM-LOST', 'hostname' => 'placeholder'])]]]]]];
    $service = voyarService(voyarServer(), [], ['voyar_ordered_at' => now()->subMinute()->toIso8601String()]);
    $state['answers']['GET /services/services/search'][0]['services']['data'][0]['hostname'] = 'viyo-'.$service->id;
    fakeVoyar($state);

    $result = (new VoyarModule)->create($service);

    expect($result['success'])->toBeTrue()
        ->and($state['calls'])->not->toContain('POST /services/services/create')
        ->and($service->fresh()->module_data['voyar_id'])->toBe('VM-LOST');
});

test('an hourly product is ordered ondemand without days', function () {
    $state = [];
    fakeVoyar($state);
    $service = voyarService(voyarServer(), ['billing' => 'ondemand']);

    (new VoyarModule)->create($service);

    $body = $state['bodies']['POST /services/services/create'];
    expect($body['type'])->toBe('ondemand')->and($body)->not->toHaveKey('days');
});

test('a yearly service is ordered for 365 days', function () {
    $state = [];
    fakeVoyar($state);
    (new VoyarModule)->create(voyarService(voyarServer(), [], [], 'annually'));

    expect($state['bodies']['POST /services/services/create']['days'])->toBe(365);
});

test('Voyar business errors come back as a failed result with its message', function () {
    $state = ['answers' => ['POST /services/services/create' => [['status' => 'failed', 'message' => 'Yetersiz bakiye.']]]];
    fakeVoyar($state);

    $result = (new VoyarModule)->create(voyarService(voyarServer()));

    expect($result['success'])->toBeFalse()->and($result['message'])->toContain('Yetersiz bakiye.');
});

test('FTP storage needs an allowed IP and stores the connection details', function () {
    $state = [];
    fakeVoyar($state);
    $service = voyarService(voyarServer(), ['voyar_kind' => 'ftp', 'package_name' => '12']);
    $module = new VoyarModule;

    expect($module->create($service)['success'])->toBeFalse();

    $field = CustomField::create(['type' => 'product', 'rel_id' => $service->product_id, 'field_name' => 'allowed_ips|İzin verilen IP', 'field_type' => 'text']);
    CustomFieldValue::create(['field_id' => $field->id, 'rel_id' => $service->id, 'value' => "203.0.113.5, not-an-ip\n198.51.100.0/24"]);

    expect($module->create($service->fresh())['success'])->toBeTrue();
    expect($state['bodies']['POST /services/ftp/create']['ip_rules'])->toBe(['203.0.113.5', '198.51.100.0/24']);
    $service->refresh();
    expect($service->username)->toBe('u77')->and($service->password)->toBe('ftp-secret')
        ->and($service->module_data['ftp_host'])->toBe('ftp1.voyar.net');
});

test('suspend stops a running server and unsuspend starts it', function () {
    $state = ['vm_state' => 'running'];
    fakeVoyar($state);
    $service = voyarService(voyarServer(), [], ['voyar_id' => 'VM-9']);
    $module = new VoyarModule;

    expect($module->suspend($service)['success'])->toBeTrue();
    expect($state['calls'])->toContain('POST /services/vm/stop/VM-9');

    $state['vm_state'] = 'stopped';
    expect($module->unsuspend($service)['success'])->toBeTrue();
    expect($state['calls'])->toContain('POST /services/vm/start/VM-9');
});

test('FTP storage is suspended by locking it', function () {
    $state = [];
    fakeVoyar($state);
    $service = voyarService(voyarServer(), ['voyar_kind' => 'ftp'], ['voyar_id' => 'FTP-1', 'voyar_kind' => 'ftp']);

    (new VoyarModule)->suspend($service);

    expect($state['calls'])->toContain('POST /services/services/lock/FTP-1');
});

test('terminate respects Voyar refusing it and is a no-op for a service never created', function () {
    $state = ['answers' => ['GET /services/services/terminate-options/VM-2' => [['status' => 'success', 'can_terminate' => false, 'message' => 'En az 24 saat kullanım gerekli.']]]];
    fakeVoyar($state);
    $module = new VoyarModule;

    $refused = $module->terminate(voyarService(voyarServer(), [], ['voyar_id' => 'VM-2']));
    expect($refused['success'])->toBeFalse()->and($refused['message'])->toContain('24 saat')
        ->and($state['calls'])->not->toContain('POST /services/services/terminate/VM-2');

    expect($module->terminate(voyarService(voyarServer()))['success'])->toBeTrue();
});

test('renew extends the prepaid period once per due date', function () {
    $state = [];
    fakeVoyar($state);
    $service = voyarService(voyarServer(), [], ['voyar_id' => 'VM-3']);
    $service->forceFill(['next_due_date' => now()->addMonth()->toDateString()])->save();
    $module = new VoyarModule;

    expect($module->renew($service)['success'])->toBeTrue();
    expect($module->renew($service->fresh())['success'])->toBeTrue();

    expect(collect($state['calls'])->filter(fn ($c) => $c === 'POST /services/services/extend/VM-3'))->toHaveCount(1);
    expect($state['bodies']['POST /services/services/extend/VM-3']['days'])->toBe(30);
});

test('hourly services are not extended on renew', function () {
    $state = [];
    fakeVoyar($state);
    $service = voyarService(voyarServer(), ['billing' => 'ondemand'], ['voyar_id' => 'VM-4']);

    expect((new VoyarModule)->renew($service)['success'])->toBeTrue()
        ->and($state['calls'])->not->toContain('POST /services/services/extend/VM-4');
});

test('the customer sees live status of their own server and nobody else does', function () {
    $state = [];
    fakeVoyar($state);
    $service = voyarService(voyarServer(), [], ['voyar_id' => 'VM-8']);
    $service->forceFill(['status' => 'active'])->save();
    $owner = User::factory()->create();
    $owner->clients()->attach($service->client_id);
    $stranger = User::factory()->create();
    $stranger->clients()->attach(Client::factory()->create()->id);

    $this->actingAs($owner)->getJson(route('voyar.client.status', $service))->assertOk()->assertJson(['ok' => true, 'state' => 'running']);
    $this->actingAs($stranger)->getJson(route('voyar.client.status', $service))->assertForbidden();
});

test('reinstall only accepts a system Voyar offers', function () {
    $state = ['answers' => ['GET /resources/operatingsystems' => [['status' => 'success', 'families' => [['id' => 1, 'name' => 'Ubuntu', 'operatingsystems' => [['id' => 13, 'name' => 'Ubuntu 24.04']]]]]]]];
    fakeVoyar($state);
    $service = voyarService(voyarServer(), [], ['voyar_id' => 'VM-6']);
    $service->forceFill(['status' => 'active'])->save();
    $owner = User::factory()->create();
    $owner->clients()->attach($service->client_id);

    $this->actingAs($owner)->post(route('voyar.client.reinstall', $service), ['osid' => '999', 'confirm' => '1'])->assertStatus(422);
    expect($state['calls'])->not->toContain('POST /services/services/reinstall/VM-6');

    $this->actingAs($owner)->post(route('voyar.client.reinstall', $service), ['osid' => '13', 'confirm' => '1'])->assertRedirect();
    expect($state['bodies']['POST /services/services/reinstall/VM-6'])->toBe(['osid' => '13']);
});
