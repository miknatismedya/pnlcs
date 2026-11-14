<?php

use Illuminate\Support\Facades\Route;
use Modules\Servers\Voyar\Http\Admin\ServersController;
use Modules\Servers\Voyar\Http\Admin\SetupController;
use Modules\Servers\Voyar\Http\ClientController;

// The customer's own server: same guards as the client area's service pages.
Route::middleware(['web', 'banned.ip', 'client.permission', 'auth', '2fa'])
    ->prefix('client/voyar/{service}')
    ->name('voyar.client.')
    ->group(function () {
        Route::get('status', [ClientController::class, 'status'])->name('status');
        Route::get('console', [ClientController::class, 'console'])->name('console');
        Route::post('reinstall', [ClientController::class, 'reinstall'])->middleware('throttle:5,1')->name('reinstall');
        Route::post('apps', [ClientController::class, 'installApp'])->middleware('throttle:10,1')->name('apps.install');
        Route::post('password', [ClientController::class, 'password'])->middleware('throttle:5,1')->name('password');
        Route::get('section/{section}', [ClientController::class, 'section'])->middleware('throttle:60,1')->name('section');
        Route::post('act/{action}', [ClientController::class, 'act'])->middleware('throttle:20,1')->where('action', '[a-z]+\\.[a-z]+')->name('act');
    });

// Staff: Setup → Voyar, the wizard tying products to Voyar plans.
Route::middleware(['web', 'admin.auth', 'admin.2fa', 'admin.permission:manage_products'])
    ->prefix('admin/voyar')
    ->name('admin.voyar.')
    ->group(function () {
        Route::get('setup', [SetupController::class, 'index'])->name('setup');
        Route::post('setup/refresh', [SetupController::class, 'refresh'])->name('setup.refresh');
        Route::post('setup/mapping', [SetupController::class, 'mapping'])->name('setup.mapping');
        Route::post('setup/options', [SetupController::class, 'options'])->name('setup.options');
        Route::post('setup/addons', [SetupController::class, 'addons'])->name('setup.addons');
        Route::post('setup/ftp', [SetupController::class, 'ftp'])->name('setup.ftp');
        Route::get('servers', [ServersController::class, 'index'])->name('servers');
        Route::post('servers/import', [ServersController::class, 'import'])->name('servers.import');
    });
