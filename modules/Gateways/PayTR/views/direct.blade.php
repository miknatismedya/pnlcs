{{--
    PayTR Direkt API card form. It posts straight to PayTR (www.paytr.com/odeme),
    never to this site, so the card number does not reach our server. The
    installment count is part of PayTR's signature: a signed token for every
    count is on the page and the chosen one goes with the form.
--}}
@extends('client.layouts.app')
@section('title', __('paytr::paytr.title'))
@section('content')
@php
    $f = $form['fields'];
    $stored = $form['stored'];
    $years = range((int) date('y'), (int) date('y') + 12);
@endphp
<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('paytr::paytr.title') }}</h1>
        <p class="pn-page-subtitle">{{ __('paytr::paytr.subtitle', ['num' => $invoice->invoice_num ?: $invoice->id, 'amount' => invoice_money_fmt($invoice->amountDue(), $invoice)]) }}</p>
    </div>
    <a href="{{ url('/client/invoices/'.$invoice->id) }}" class="btn btn-outline btn-sm">← {{ __('paytr::paytr.back') }}</a>
</div>

@if($form['test'])
<div class="pn-alert pn-alert-warning">
    <div>
        <b>{{ __('paytr::paytr.test_title') }}</b> {{ __('paytr::paytr.test_text') }}
        <div style="margin-top:6px;font-family:monospace">@foreach($testCards as $card){{ $card }}@if(!$loop->last) · @endif @endforeach — 12/30 · CVV 000</div>
    </div>
</div>
@endif

<div style="display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:20px;align-items:start" class="paytr-grid">
<form method="POST" action="{{ $action }}" id="paytrForm" class="pn-card">
    <div class="pn-card-body">
        @foreach(\Illuminate\Support\Arr::except($f, ['utoken']) as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}" @if(in_array($name, ['paytr_token', 'installment_count'], true)) id="paytr_{{ $name }}" @endif>
        @endforeach
        <input type="hidden" name="card_type" id="paytr_card_type" value="">
        @if(!empty($f['utoken']))<input type="hidden" name="utoken" value="{{ $f['utoken'] }}">@endif

        @if($stored->isNotEmpty())
        <div class="form-group">
            <label class="form-label">{{ __('paytr::paytr.pay_with') }}</label>
            @foreach($stored as $card)
            <label style="display:flex;gap:10px;align-items:center;padding:10px 12px;border:1px solid var(--border);border-radius:8px;margin-bottom:8px;cursor:pointer">
                <input type="radio" name="paytr_card_choice" value="{{ $card->remote_token }}" data-cvv="{{ ($needCvv[$card->remote_token] ?? true) ? 1 : 0 }}" @checked($loop->first)>
                <span><b>{{ $card->card_brand ?: 'Kart' }}</b> •••• {{ $card->last_four }} @if($card->exp_month)<small class="text-muted">{{ sprintf('%02d', $card->exp_month) }}/{{ substr((string) $card->exp_year, -2) }}</small>@endif</span>
            </label>
            @endforeach
            <label style="display:flex;gap:10px;align-items:center;padding:10px 12px;border:1px solid var(--border);border-radius:8px;cursor:pointer">
                <input type="radio" name="paytr_card_choice" value="new"> <span>{{ __('paytr::paytr.new_card') }}</span>
            </label>
            <input type="hidden" name="ctoken" id="paytr_ctoken" value="">
            <div class="form-group" id="paytrStoredCvv" style="margin-top:10px;max-width:160px">
                <label class="form-label">CVV</label>
                <input type="text" name="cvv" class="form-control" inputmode="numeric" maxlength="4" autocomplete="cc-csc">
            </div>
        </div>
        @endif

        <div id="paytrNewCard">
            <div class="form-group">
                <label class="form-label">{{ __('paytr::paytr.cc_owner') }}</label>
                <input type="text" name="cc_owner" class="form-control" maxlength="50" autocomplete="cc-name" required>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('paytr::paytr.card_number') }}</label>
                <input type="text" name="card_number" id="paytrNumber" class="form-control" inputmode="numeric" maxlength="19" autocomplete="cc-number" required>
                <small class="form-hint" id="paytrBank"></small>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                <div class="form-group">
                    <label class="form-label">{{ __('paytr::paytr.month') }}</label>
                    <select name="expiry_month" class="form-control" required autocomplete="cc-exp-month">
                        @foreach(range(1, 12) as $m)<option value="{{ $m }}">{{ sprintf('%02d', $m) }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('paytr::paytr.year') }}</label>
                    <select name="expiry_year" class="form-control" required autocomplete="cc-exp-year">
                        @foreach($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">CVV</label>
                    <input type="text" name="cvv" class="form-control" inputmode="numeric" maxlength="4" autocomplete="cc-csc" required>
                </div>
            </div>
            @if($form['store_cards'])
            <label style="display:flex;gap:8px;align-items:center;margin:4px 0 14px">
                <input type="checkbox" name="store_card" value="1"> {{ __('paytr::paytr.store_card') }}
            </label>
            @endif
        </div>

        <div class="form-group" id="paytrInstallments" style="display:none">
            <label class="form-label">{{ __('paytr::paytr.installments') }}</label>
            <select class="form-control" id="paytrCount"><option value="0">{{ __('paytr::paytr.single') }}</option></select>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%">{{ __('paytr::paytr.pay_button', ['amount' => invoice_money_fmt($invoice->amountDue(), $invoice)]) }}</button>
        <p class="text-muted text-sm" style="margin-top:10px"><i class="ri-lock-2-line"></i> {{ __('paytr::paytr.direct_secure') }}</p>
    </div>
</form>

<aside class="pn-card">
    <div class="pn-card-body">
        <div class="text-muted text-sm">{{ __('paytr::paytr.summary') }}</div>
        <div style="font-size:24px;font-weight:700;margin:6px 0">{{ invoice_money_fmt($invoice->amountDue(), $invoice) }}</div>
        <div class="text-muted text-sm">{{ __('paytr::paytr.basket_item', ['num' => $invoice->invoice_num ?: $invoice->id]) }}</div>
    </div>
</aside>
</div>
<style>@media (max-width: 860px) { .paytr-grid { grid-template-columns: 1fr !important; } }</style>

<script>
(function () {
    var tokens = @json($form['tokens']);
    var form = document.getElementById('paytrForm');
    var newCard = document.getElementById('paytrNewCard');
    var storedCvv = document.getElementById('paytrStoredCvv');
    var ctoken = document.getElementById('paytr_ctoken');
    var box = document.getElementById('paytrInstallments');
    var count = document.getElementById('paytrCount');
    var lastBin = '';

    function setCount(n) {
        document.getElementById('paytr_installment_count').value = n;
        document.getElementById('paytr_paytr_token').value = tokens[n] || tokens[0];
    }
    function enable(el, on) {
        el.style.display = on ? '' : 'none';
        el.querySelectorAll('input,select').forEach(function (i) { i.disabled = !on; });
    }
    function choose() {
        var picked = form.querySelector('input[name=paytr_card_choice]:checked');
        var stored = picked && picked.value !== 'new';
        enable(newCard, !stored);
        if (storedCvv) enable(storedCvv, stored && picked.dataset.cvv === '1');
        if (ctoken) { ctoken.value = stored ? picked.value : ''; ctoken.disabled = !stored; }
        if (stored) { box.style.display = 'none'; setCount(0); }
    }
    form.querySelectorAll('input[name=paytr_card_choice]').forEach(function (r) { r.addEventListener('change', choose); });
    choose();

    count.addEventListener('change', function () { setCount(count.value); });

    document.getElementById('paytrNumber').addEventListener('input', function () {
        var digits = this.value.replace(/\D/g, '');
        this.value = digits.replace(/(\d{4})(?=\d)/g, '$1 ');
        var bin = digits.slice(0, 8);
        if (bin.length < 8 || bin === lastBin) return;
        lastBin = bin;
        fetch(@json(route('paytr.bin', $invoice)) + '?bin=' + bin, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                document.getElementById('paytr_card_type').value = d.brand || '';
                document.getElementById('paytrBank').textContent = d.bank || '';
                count.innerHTML = '<option value="0">' + @json(__('paytr::paytr.single')) + '</option>';
                (d.installments || []).forEach(function (o) {
                    var opt = document.createElement('option');
                    opt.value = o.count;
                    opt.textContent = o.count + ' x ' + o.each + (o.rate !== null ? '  (%' + o.rate + ')' : '');
                    count.appendChild(opt);
                });
                box.style.display = (d.installments || []).length ? '' : 'none';
                setCount(0);
            })
            .catch(function () { box.style.display = 'none'; setCount(0); });
    });

    // The card number goes without the spaces typed for readability.
    form.addEventListener('submit', function () {
        var n = document.getElementById('paytrNumber');
        if (!n.disabled) n.value = n.value.replace(/\D/g, '');
    });
})();
</script>
@endsection
