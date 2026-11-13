<?php

use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use App\Services\ThemeManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Addons\ViyoCloud\ViyoCloudServiceProvider;
use Tests\TestCase;

/*
 * The Viyo theme dresses the core client pages (themes/viyo/views/viyo/decorate)
 * instead of copying them: the core page must still be all there.
 */

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    $this->app->register(ViyoCloudServiceProvider::class);
    app('router')->getRoutes()->refreshNameLookups();
    Setting::set('active_theme_slug', 'viyo', 'appearance');
    app(ThemeManager::class)->applyViewPaths();
    app('view')->getFinder()->flush();
});

test('the sign-in page keeps the core form and gains the brand panel', function () {
    $html = $this->get('/client/login')->assertOk()->getContent();

    expect($html)->toContain('class="vx vx--auth vx--out"')
        ->toContain('vx-auth__aside')
        ->toContain('/themes/viyo/assets/css/theme.css')
        ->toContain('action="'.route('client.login.submit').'"')
        ->toContain('name="password"')
        ->and(substr_count($html, '<body'))->toBe(1);
});

test('a guest gets the site header in place of the core menu bar', function () {
    $html = $this->get('/client/store')->assertOk()->getContent();

    expect($html)->toContain('vx-head')
        ->not->toContain('class="pn-navbar"')
        ->not->toContain('class="pn-footer"')
        ->toContain('vx-foot');
});

test('a signed-in customer gets the sidebar, the balance and the page in one column', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['credit' => 42.5]);
    $user->clients()->attach($client->id, ['owner' => true]);

    $html = $this->actingAs($user)->get('/client')->assertOk()->getContent();

    expect($html)->toContain('vx-side')
        ->toContain('vx-balance')
        ->toContain('<div class="vx-page">')
        ->toContain('class="pn-main"')
        ->not->toContain('<!--vx:')
        ->and(substr_count($html, '<div class="vx-page">'))->toBe(1);
});
