<?php

use Illuminate\Support\Facades\Route;
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
