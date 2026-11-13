@extends('client.layouts.app')
@section('title', __('viyocloud::site.hourly_order.usage_title'))
@section('content')
<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('viyocloud::site.hourly_order.usage_title') }}</h1>
        <p class="pn-page-subtitle">{{ $service->product?->name }} · #{{ $service->id }} @if($service->domain)· {{ $service->domain }}@endif</p>
    </div>
    <a href="{{ route('client.services.show', $service) }}" class="pn-btn pn-btn-sm">← {{ __('voyar::voyar.console_back') }}</a>
</div>

@if(session('success'))<div class="pn-alert pn-alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="pn-alert pn-alert-danger">{{ session('error') }}</div>@endif

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px">
    @foreach([
        'rate' => \Modules\Addons\ViyoCloud\Support\Catalog::hourlyFmt((float) $row->rate),
        'stopped_rate' => \Modules\Addons\ViyoCloud\Support\Catalog::hourlyFmt((float) $row->stopped_rate),
        'total' => money_fmt((float) $row->total),
        'owed' => money_fmt((float) $row->owed),
    ] as $key => $value)
    <div class="pn-card"><div class="pn-card-body"><div class="text-muted" style="font-size:12px">{{ __('viyocloud::site.hourly_order.stat.'.$key) }}</div><div style="font-size:22px;font-weight:800">{{ $value }}</div></div></div>
    @endforeach
</div>

<div class="pn-card mb-24">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('viyocloud::site.hourly_order.charges') }}</span></div>
    <div class="pn-card-body" style="padding:0">
        <table class="pn-table">
            <thead><tr><th>{{ __('viyocloud::site.hourly_order.period') }}</th><th>{{ __('viyocloud::site.hourly_order.state') }}</th><th style="text-align:right">{{ __('viyocloud::site.hourly_order.amount') }}</th></tr></thead>
            <tbody>
            @forelse($charges as $c)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($c->period_start)->format('d.m.Y H:i') }} – {{ \Carbon\Carbon::parse($c->period_end)->format('H:i') }}</td>
                    <td>{{ $c->running ? __('voyar::voyar.panel.states.running') : __('voyar::voyar.panel.states.stopped') }}</td>
                    <td style="text-align:right">{{ \Modules\Addons\ViyoCloud\Support\Catalog::hourlyFmt((float) $c->amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-muted" style="text-align:center;padding:24px">{{ __('viyocloud::site.hourly_order.no_charges') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $charges->links() }}

@if(! $row->ended_at)
<div class="pn-card" style="border-color:#fecaca">
    <div class="pn-card-header"><span class="pn-card-title" style="color:#b91c1c">{{ __('viyocloud::site.hourly_order.delete_title') }}</span></div>
    <form class="pn-card-body" method="POST" action="{{ route('viyocloud.hourly.destroy', $service) }}">
        @csrf
        <p class="text-muted">{{ __('viyocloud::site.hourly_order.delete_text') }}</p>
        @if($row->terminate_requested_at)<div class="pn-alert pn-alert-warning">{{ __('viyocloud::site.hourly_order.delete_waiting') }}</div>@endif
        <label style="display:flex;gap:8px;font-size:13px;margin-bottom:12px"><input type="checkbox" name="confirm" value="1" required> {{ __('viyocloud::site.hourly_order.delete_confirm') }}</label>
        <button class="btn" style="background:#e5484d;color:#fff">{{ __('viyocloud::site.hourly_order.delete_submit') }}</button>
    </form>
</div>
@endif
@endsection
