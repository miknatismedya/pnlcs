<?php

use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use App\Services\ThemeManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Addons\ViyoCloud\ViyoCloudServiceProvider;
use Tests\TestCase;

/*
 * The Viyo theme replaces core client views the pnlcs way (themes/viyo/views,
 * see docs/developer/themes.md); the core parts of each page must still be there.
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

    expect($html)->toContain('vs-header')
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
        ->and(substr_count($html, '<div class="vx-page">'))->toBe(1);
});

test('every view the theme replaces has a core original, so Setup → Updates can track it', function () {
    $dir = base_path('themes/viyo/views');
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        $relative = substr($file->getPathname(), strlen($dir) + 1);
        // The theme's own partials are named viyo-*; everything else replaces a core view.
        if (str_starts_with($file->getFilename(), 'viyo-')) {
            continue;
        }
        expect(file_exists(resource_path('views/'.$relative)))->toBeTrue("no core view for {$relative}");
    }
});

test('the homepage is drawn from the homepage sections, and switching one off removes it', function () {
    $html = $this->get('/')->assertOk()->getContent();
    expect($html)->toContain('vs-hero')->toContain('id="plans"')->toContain('vs-footer');

    \App\Models\HomepageSection::where('slug', 'viyo-plans')->update(['is_enabled' => false]);
    expect($this->get('/')->assertOk()->getContent())->not->toContain('id="plans"');
});

test('an operator\'s wording in Setup → Appearance replaces the translated default', function () {
    \App\Models\HomepageContent::where('section_slug', 'viyo-lines')->where('content_key', 'title')->update(['content_value' => 'Bizim başlığımız']);

    expect($this->get('/')->assertOk()->getContent())->toContain('Bizim başlığımız');
});
