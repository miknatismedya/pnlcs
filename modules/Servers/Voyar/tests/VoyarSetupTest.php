<?php

use App\Models\Admin;
use App\Models\ConfigOptionGroup;
use App\Models\ConfigOptionLink;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Addons\ViyoCloud\Http\HourlyController;
use Modules\Servers\Voyar\VoyarSetup;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

function setupFakeVoyar(): void
{
    Http::fake(function (Request $request) {
        $path = substr(parse_url($request->url(), PHP_URL_PATH), strlen('/client'));

        return match ($path) {
            '/auth/login' => Http::response(['status' => 'success', 'token' => ['token' => 't']]),
            '/auth/token' => Http::response(['status' => 'success', 'client' => ['credit' => 150.5]]),
            '/resources/products/search' => Http::response(['status' => 'success', 'products' => ['data' => [
                ['id' => 3, 'name' => 'CV-10', 'kind' => 'vm', 'features' => ['2 vCPU', '2 GB RAM', '40 GB NVMe'], 'price' => ['ondemand' => 0.1964, 'prepaid' => 4.18], 'product_group' => ['name' => 'NVMe Cloud']],
                ['id' => 4, 'name' => 'CV-20', 'kind' => 'vm', 'features' => ['4 vCPU', '4 GB RAM', '60 GB NVMe'], 'price' => ['ondemand' => 0.3878, 'prepaid' => 8.36], 'product_group' => ['name' => 'NVMe Cloud']],
                ['id' => 9, 'name' => 'VPN', 'kind' => 'vpn', 'features' => []],
            ]]]),
            '/resources/operatingsystems' => Http::response(['status' => 'success', 'families' => [['id' => 1, 'name' => 'Ubuntu', 'operatingsystems' => [['id' => 13, 'name' => 'Ubuntu 24.04'], ['id' => 12, 'name' => 'Ubuntu 22.04']]]]]),
            '/resources/locations/search' => Http::response(['status' => 'success', 'locations' => ['data' => [['id' => 1, 'name' => 'Istanbul']]]]),
            default => Http::response(['status' => 'success']),
        };
    });
}

function setupProduct(int $cpu, int $ram, int $disk): Product
{
    return Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id, 'type' => 'vps', 'retired' => false,
        'config_options' => ['cpu' => $cpu, 'ram' => $ram, 'disk' => $disk, 'hourly_rate' => 0.255],
    ]);
}

beforeEach(fn () => Cache::flush());

test('a Voyar plan with the same resources is suggested', function () {
    $plans = [['id' => 3, 'features' => ['2 vCPU', '2 GB RAM', '40 GB NVMe']], ['id' => 4, 'features' => ['4 vCPU', '4 GB RAM', '60 GB NVMe']]];

    expect(VoyarSetup::suggest(setupProduct(4, 4, 60), $plans))->toBe('4')
        ->and(VoyarSetup::suggest(setupProduct(3, 3, 30), $plans))->toBeNull();
});

test('mapping ties products to the voyar module and its plan', function () {
    $product = setupProduct(2, 2, 40);

    expect((new VoyarSetup)->applyMapping([$product->id => '3', 999999 => '4'], [$product->id => 'vm']))->toBe(1);

    $product->refresh();
    expect($product->server_type)->toBe('voyar')
        ->and($product->config_options['package_name'])->toBe('3')
        ->and($product->config_options['voyar_kind'])->toBe('vm')
        ->and($product->config_options['hourly_rate'])->toBe(0.255);
});

test('order options are built once, rebuilt in place and offered on the hourly order form', function () {
    $product = setupProduct(2, 2, 40);
    $setup = new VoyarSetup;

    $setup->buildOptions(['location' => ['1' => 'Istanbul'], 'os' => ['13' => 'Ubuntu 24.04', '12' => 'Ubuntu 22.04'], 'cpu' => ['amd' => 'AMD EPYC']], [$product->id]);
    $setup->buildOptions(['location' => ['1' => 'Istanbul'], 'os' => ['13' => 'Ubuntu 24.04'], 'cpu' => ['amd' => 'AMD EPYC', 'intel' => 'Intel Xeon']], [$product->id]);

    expect(ConfigOptionGroup::where('name', VoyarSetup::GROUP_NAME)->count())->toBe(1)
        ->and(ConfigOptionLink::where('product_id', $product->id)->count())->toBe(1);

    $choices = HourlyController::choices($product->fresh());
    expect(array_keys($choices))->toBe(['location', 'os', 'cpu'])
        ->and($choices['os']['values'])->toBe(['13' => 'Ubuntu 24.04'])
        ->and($choices['cpu']['values'])->toBe(['amd' => 'AMD EPYC', 'intel' => 'Intel Xeon']);
});

test('the wizard page shows the catalogue and saves choices with Voyar\'s own labels', function () {
    setupFakeVoyar();
    Server::factory()->create(['type' => 'voyar', 'hostname' => 'api.voyar.net', 'ip_address' => '', 'username' => 'r@example.com', 'password' => 'p', 'access_hash' => '', 'active' => true, 'disabled' => false]);
    $product = setupProduct(2, 2, 40);
    $admin = Admin::first() ?? Admin::factory()->create();

    $this->actingAs($admin, 'admin')->get(route('admin.voyar.setup'))
        ->assertOk()->assertSee('CV-10')->assertSee('Ubuntu 24.04')->assertDontSee('>VPN<', false);

    $this->actingAs($admin, 'admin')->post(route('admin.voyar.setup.mapping'), ['map' => [$product->id => '3'], 'kind' => [$product->id => 'vm']])->assertRedirect();
    expect($product->fresh()->server_type)->toBe('voyar');

    // A label sent by the form is ignored; an id Voyar does not offer is dropped.
    $this->actingAs($admin, 'admin')->post(route('admin.voyar.setup.options'), [
        'location' => ['1'], 'os' => ['13', '666'], 'cpu' => ['amd'], 'products' => [$product->id],
    ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('error')->assertSessionHas('success');

    expect(HourlyController::choices($product->fresh())['os']['values'])->toBe(['13' => 'Ubuntu 24.04']);
});
