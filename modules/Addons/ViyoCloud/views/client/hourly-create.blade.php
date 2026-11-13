@extends('client.layouts.app')
@section('title', __('viyocloud::site.hourly_order.title'))
@section('content')
@php
    $planData = $plans->mapWithKeys(fn ($p) => [$p['slug'] => ['name' => $p['name'], 'rate' => $p['hourly_raw'], 'rate_text' => $p['hourly'], 'month' => $p['hourly_month']]]);
@endphp
<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('viyocloud::site.hourly_order.title') }}</h1>
        <p class="pn-page-subtitle">{{ __('viyocloud::site.hourly_order.subtitle') }}</p>
    </div>
</div>

@if(session('error'))<div class="pn-alert pn-alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())<div class="pn-alert pn-alert-danger"><ul style="margin:0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ route('viyocloud.hourly.store') }}" x-data="{ plan: @js(old('plan', $plan['slug'])), plans: @js($planData), balance: {{ $balance }}, min: {{ $settings['min_hours'] }} }">
    @csrf
    <div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(260px,1fr);gap:20px;align-items:start" class="vh-grid">
        <div>
            <div class="pn-card mb-24">
                <div class="pn-card-header"><span class="pn-card-title">{{ __('viyocloud::site.hourly_order.plan') }}</span></div>
                <div class="pn-card-body" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px">
                    @foreach($plans as $p)
                    <label style="display:block;border:1.5px solid var(--border);border-radius:12px;padding:12px;cursor:pointer" :style="plan === @js($p['slug']) ? 'border-color:var(--primary);background:var(--primary-light)' : ''">
                        <input type="radio" name="plan" value="{{ $p['slug'] }}" x-model="plan" style="display:none">
                        <b>{{ $p['name'] }}</b>
                        <div style="font-size:12px;color:var(--muted)">{{ $p['cpu'] }} vCPU · {{ $p['ram'] }} GB · {{ $p['disk'] }} GB</div>
                        <div style="font-weight:800;margin-top:6px">{{ $p['hourly'] }}<small style="font-weight:500;color:var(--muted)">{{ __('viyocloud::site.common.per_hour') }}</small></div>
                    </label>
                    @endforeach
                </div>
            </div>

            <div class="pn-card mb-24">
                <div class="pn-card-header"><span class="pn-card-title">{{ __('viyocloud::site.hourly_order.setup') }}</span></div>
                <div class="pn-card-body">
                    @forelse($choices as $key => $choice)
                    <div class="form-group" style="margin-bottom:14px">
                        <label class="form-label">{{ $choice['title'] }}</label>
                        <select name="options[{{ $key }}]" class="form-control">
                            @if($key === 'app')<option value="">{{ __('viyocloud::site.hourly_order.no_app') }}</option>@endif
                            @foreach($choice['values'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('options.'.$key) === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @empty
                    <p class="text-muted">{{ __('viyocloud::site.hourly_order.no_choices') }}</p>
                    @endforelse
                    <div class="form-group">
                        <label class="form-label">{{ __('viyocloud::site.hourly_order.hostname') }}</label>
                        <input type="text" name="hostname" class="form-control" maxlength="63" value="{{ old('hostname') }}" placeholder="srv01">
                    </div>
                </div>
            </div>
        </div>

        <div class="pn-card">
            <div class="pn-card-header"><span class="pn-card-title">{{ __('viyocloud::site.hourly_order.summary') }}</span></div>
            <div class="pn-card-body">
                <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span class="text-muted">{{ __('viyocloud::site.hourly.table_plan') }}</span><b x-text="plans[plan].name"></b></div>
                <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span class="text-muted">{{ __('viyocloud::site.hourly.table_hourly') }}</span><b x-text="plans[plan].rate_text"></b></div>
                <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span class="text-muted">{{ __('viyocloud::site.hourly.table_month') }}</span><span x-text="plans[plan].month"></span></div>
                <div style="display:flex;justify-content:space-between;margin-bottom:16px"><span class="text-muted">{{ __('viyocloud::site.hourly_order.balance') }}</span><b>{{ money_fmt($balance) }}</b></div>
                <p style="font-size:13px" x-show="plans[plan].rate > 0">{{ __('viyocloud::site.hourly_order.lasts') }} <b x-text="Math.floor(balance / plans[plan].rate)"></b> {{ __('viyocloud::site.hourly_order.hours') }}</p>
                <div class="pn-alert pn-alert-warning" x-show="balance < plans[plan].rate * min" style="display:none">
                    {{ __('viyocloud::site.hourly_order.low') }} <a href="{{ route('client.funds.index') }}">{{ __('client.add_funds') }} →</a>
                </div>
                <ul style="font-size:12.5px;color:var(--muted);padding-left:18px;margin:12px 0">
                    @foreach(__('viyocloud::site.hourly.rules') as $rule)<li style="margin-bottom:6px">{{ $rule }}</li>@endforeach
                </ul>
                <label style="display:flex;gap:8px;font-size:13px;margin-bottom:14px"><input type="checkbox" name="accept" value="1" required> {{ __('viyocloud::site.hourly_order.accept') }}</label>
                <button type="submit" class="btn btn-primary" style="width:100%" :disabled="balance < plans[plan].rate * min">{{ __('viyocloud::site.hourly_order.submit') }}</button>
            </div>
        </div>
    </div>
</form>
<style>@media (max-width: 860px) { .vh-grid { grid-template-columns: 1fr !important; } }</style>
@endsection
