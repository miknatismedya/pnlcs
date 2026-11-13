<?php

namespace Modules\Gateways\PayTR\Http;

use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Request;
use Modules\Gateways\PayTR\PayTRModule;

/**
 * The customer's payment page: PayTR's own form in an iframe (iFrame API),
 * or our card form posting straight to PayTR (Direkt API).
 */
class PayController extends Controller
{
    use ResolvesClient;

    public function show(Request $request, Invoice $invoice, ModuleRegistry $registry)
    {
        $this->guard($invoice, $registry);
        if ($invoice->amountDue() <= 0) {
            return redirect('/client/invoices/'.$invoice->id);
        }
        $module = new PayTRModule;

        if ($module->settings()['integration'] === 'direct') {
            $form = $module->directForm($invoice, (string) $request->ip());
            if (! $form['success']) {
                return redirect('/client/invoices/'.$invoice->id)->with('error', __('paytr::paytr.failed', ['reason' => $form['message'] ?? '']));
            }

            // Which stored cards need their CVV again: one card-list call.
            $needCvv = [];
            if ($form['stored']->isNotEmpty() && ($utoken = $form['fields']['utoken'] ?? null)) {
                foreach ($module->listCards($utoken) as $card) {
                    $needCvv[(string) ($card['ctoken'] ?? '')] = (string) ($card['require_cvv'] ?? '1') === '1';
                }
                $form['stored'] = $form['stored']->filter(fn ($m) => array_key_exists((string) $m->remote_token, $needCvv))->values();
            }

            return view('paytr::direct', [
                'invoice' => $invoice,
                'form' => $form,
                'needCvv' => $needCvv,
                'action' => PayTRModule::DIRECT_URL,
                'counts' => $module->installmentCounts(),
                'testCards' => PayTRModule::TEST_CARDS,
            ]);
        }

        $result = $module->getToken($invoice, (string) $request->ip());
        if (! $result['success']) {
            return redirect('/client/invoices/'.$invoice->id)->with('error', __('paytr::paytr.failed', ['reason' => $result['message'] ?? '']));
        }

        return view('paytr::pay', ['invoice' => $invoice, 'iframe' => PayTRModule::IFRAME_URL.$result['token']]);
    }

    /**
     * The card family for the first digits typed, and the installment
     * counts with their rates for it. Only the BIN (6-8 digits) is sent here.
     */
    public function bin(Request $request, Invoice $invoice, ModuleRegistry $registry)
    {
        $this->guard($invoice, $registry);
        $bin = preg_replace('/\D/', '', (string) $request->query('bin'));
        abort_if(strlen($bin) < 6 || strlen($bin) > 8, 422);

        $module = new PayTRModule;
        $detail = $module->binDetail($bin);
        $brand = $detail['brand'] ?? 'none';
        // Installments are for personal credit cards of a known family.
        $canSplit = ($detail['success'] ?? false) && $brand !== 'none' && ($detail['card_type'] ?? '') === 'credit';
        $rates = $canSplit ? ($module->installmentRates()[$brand] ?? []) : [];
        $due = $invoice->amountDue();

        $options = [];
        foreach ($module->installmentCounts() as $count) {
            if ($count === 0) {
                continue;
            }
            if (! $canSplit) {
                break;
            }
            $options[] = [
                'count' => $count,
                'rate' => $rates[$count] ?? null,
                'each' => invoice_money_fmt(round($due / $count, 2), $invoice),
            ];
        }

        return response()->json([
            'brand' => $canSplit ? $brand : '',
            'bank' => $detail['bank'] ?? '',
            'installments' => $options,
        ]);
    }

    private function guard(Invoice $invoice, ModuleRegistry $registry): void
    {
        abort_if($invoice->client_id !== $this->getClientId(), 403);
        abort_unless(in_array(PayTRModule::NAME, array_map('strtolower', $registry->usableGateways()), true), 404);
    }
}
