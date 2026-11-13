@extends('client.layouts.app')
@section('title', __('paytr::paytr.title'))
@section('content')
<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('paytr::paytr.title') }}</h1>
        <p class="pn-page-subtitle">{{ __('paytr::paytr.subtitle', ['num' => $invoice->invoice_num ?: $invoice->id, 'amount' => invoice_money_fmt($invoice->amountDue(), $invoice)]) }}</p>
    </div>
    <a href="{{ url('/client/invoices/'.$invoice->id) }}" class="pn-btn pn-btn-sm">← {{ __('paytr::paytr.back') }}</a>
</div>
<div class="pn-card">
    <div class="pn-card-body" style="padding:8px">
        <script src="https://www.paytr.com/js/iframeResizer.min.js"></script>
        <iframe src="{{ $iframe }}" id="paytriframe" frameborder="0" scrolling="no" style="width:100%;min-height:600px;border:0"></iframe>
        <script>iFrameResize({}, '#paytriframe');</script>
    </div>
</div>
<p class="text-muted text-sm" style="margin-top:12px"><i class="ri-lock-2-line"></i> {{ __('paytr::paytr.secure') }}</p>
@endsection
