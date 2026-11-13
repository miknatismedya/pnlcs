<?php

use Illuminate\Support\Facades\Route;
use Modules\Gateways\PayTR\Http\PayController;

// The payment page (PayTR's iFrame). Not named gateway.paytr.*: that would
// switch off the generic /gateway/paytr/callback PayTR posts its result to.
Route::middleware(['web', 'banned.ip', 'client.permission', 'auth', '2fa'])
    ->get('client/paytr/pay/{invoice}', [PayController::class, 'show'])
    ->middleware('throttle:20,1')
    ->name('paytr.pay');
