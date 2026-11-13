<?php

use Illuminate\Support\Facades\Route;
use Modules\Addons\ViyoCloud\Http\HourlyController;
use Modules\Addons\ViyoCloud\Http\SiteController;

// Public pages. Language-neutral paths: with "language in the address" on,
// PNLCS serves them under /en/... as well.
Route::middleware('web')->name('viyocloud.')->group(function () {
    Route::get('cloud', [SiteController::class, 'cloud'])->name('cloud');
    Route::get('cloud/hourly', [SiteController::class, 'hourly'])->name('hourly');
    Route::get('ftp-storage', [SiteController::class, 'ftp'])->name('ftp');
    Route::get('apps', [SiteController::class, 'apps'])->name('apps');
    Route::get('infrastructure', [SiteController::class, 'infrastructure'])->name('infrastructure');
});

// Hourly servers: the client area's guards.
Route::middleware(['web', 'banned.ip', 'client.permission', 'auth', '2fa'])
    ->prefix('client/hourly')->name('viyocloud.hourly.')
    ->group(function () {
        Route::get('new', [HourlyController::class, 'create'])->name('create');
        Route::post('new', [HourlyController::class, 'store'])->middleware('throttle:5,1')->name('store');
        Route::get('{service}', [HourlyController::class, 'usage'])->name('usage');
        Route::post('{service}/delete', [HourlyController::class, 'destroy'])->middleware('throttle:5,1')->name('destroy');
    });
