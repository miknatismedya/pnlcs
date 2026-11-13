<?php

namespace Modules\Gateways\PayTR\Http\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Gateways\PayTR\PayTRModule;

/** Admin: ask PayTR about a payment (durum-sorgu) and see the installment rates. */
class StatusController extends Controller
{
    public function index(Request $request)
    {
        $module = new PayTRModule;
        $oid = trim((string) $request->query('merchant_oid', ''));
        $result = $oid !== '' && preg_match('/^[0-9A-Za-z]{1,64}$/', $oid) ? $module->statusQuery($oid) : null;

        return view('paytr::admin.status', [
            'oid' => $oid,
            'result' => $result,
            'rates' => $module->installmentRates(),
            'settings' => $module->settings(),
        ]);
    }
}
