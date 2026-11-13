<?php

use Illuminate\Support\Facades\Route;
use Modules\Gateways\PayTR\Http\Admin\StatusController;
use Modules\Gateways\PayTR\Http\PayController;

// The payment page and its card-family lookup. Not named gateway.paytr.*:
// that would switch off the generic /gateway/paytr/callback PayTR posts its
// result to.
Route::middleware(['web', 'banned.ip', 'client.permission', 'auth', '2fa'])->group(function () {
    Route::get('client/paytr/pay/{invoice}', [PayController::class, 'show'])->middleware('throttle:20,1')->name('paytr.pay');
    Route::get('client/paytr/bin/{invoice}', [PayController::class, 'bin'])->middleware('throttle:30,1')->name('paytr.bin');
});

Route::middleware(['web', 'admin.auth', 'admin.2fa', 'admin.permission:manage_settings'])
    ->get('admin/paytr/status', [StatusController::class, 'index'])
    ->name('admin.paytr.status');
