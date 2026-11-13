<?php

namespace Modules\Gateways\PayTR\Http;

use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Request;
use Modules\Gateways\PayTR\PayTRModule;

/** The customer's payment page: PayTR's own card form in an iframe. */
class PayController extends Controller
{
    use ResolvesClient;

    public function show(Request $request, Invoice $invoice, ModuleRegistry $registry)
    {
        abort_if($invoice->client_id !== $this->getClientId(), 403);
        abort_unless(in_array(PayTRModule::NAME, array_map('strtolower', $registry->usableGateways()), true), 404);

        if ($invoice->amountDue() <= 0) {
            return redirect('/client/invoices/'.$invoice->id);
        }

        $result = (new PayTRModule)->getToken($invoice, (string) $request->ip());
        if (! $result['success']) {
            return redirect('/client/invoices/'.$invoice->id)->with('error', __('paytr::paytr.failed', ['reason' => $result['message'] ?? '']));
        }

        return view('paytr::pay', ['invoice' => $invoice, 'iframe' => PayTRModule::IFRAME_URL.$result['token']]);
    }
}
