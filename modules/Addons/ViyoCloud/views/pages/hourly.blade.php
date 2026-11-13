@extends('viyocloud::layout')

@section('title', __('viyocloud::site.hourly.title'))
@section('meta_description', __('viyocloud::site.hourly.lead'))

@php
    $hourlyOpen = \Modules\Addons\ViyoCloud\Support\HourlyBilling::settings()['enabled'];
    $calc = $plans->map(fn ($p) => ['name' => $p['name'], 'rate' => $p['hourly_raw']])->values();
    $currency = \App\Models\Currency::getDefault();
    [$dec, $thou] = \App\Models\Currency::FORMATS[(int) ($currency->format ?? 1)] ?? \App\Models\Currency::FORMATS[1];
@endphp

@section('content')
@include('viyocloud::partials.page-hero', ['icon' => 'ri-timer-flash-line', 'title' => __('viyocloud::site.hourly.title'), 'lead' => __('viyocloud::site.hourly.lead')])

<section class="vc-section vc-section--flush">
    <div class="vc-wrap">
        @if($plans->isEmpty())
            @include('viyocloud::partials.empty')
        @else
        @unless($hourlyOpen)
        <div class="vc-notice"><i class="ri-information-line"></i><div><b>{{ __('viyocloud::site.hourly.soon_title') }}</b><p>{{ __('viyocloud::site.hourly.soon_text') }} <a href="{{ route('viyocloud.cloud') }}">{{ __('viyocloud::site.nav.cloud') }} →</a></p></div></div>
        @endunless
        <div class="vc-table-wrap">
            <table class="vc-table">
                <thead><tr><th>{{ __('viyocloud::site.hourly.table_plan') }}</th><th>{{ __('viyocloud::site.hourly.table_resources') }}</th><th>{{ __('viyocloud::site.hourly.table_hourly') }}</th><th>{{ __('viyocloud::site.hourly.table_month') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($plans as $plan)
                <tr class="{{ $plan['featured'] ? 'is-featured' : '' }}">
                    <td><b>{{ $plan['name'] }}</b>@if($plan['featured']) <span class="vc-tag">{{ __('viyocloud::site.common.popular') }}</span>@endif</td>
                    <td>@include('viyocloud::partials.specs', ['plan' => $plan])</td>
                    <td class="vc-table__price">{{ $plan['hourly'] }}<small>{{ __('viyocloud::site.common.per_hour') }}</small></td>
                    <td>{{ $plan['hourly_month'] }}</td>
                    <td>
                        @if($hourlyOpen)
                        <a href="{{ route('viyocloud.hourly.create', ['plan' => $plan['slug']]) }}" class="vc-btn vc-btn--primary vc-btn--sm">{{ __('viyocloud::site.hourly.start') }}</a>
                        @else
                        <span class="vc-btn vc-btn--ghost vc-btn--sm is-disabled" aria-disabled="true">{{ __('viyocloud::site.common.coming_soon') }}</span>
                        @endif
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="vc-note">{{ __('viyocloud::site.common.vat_note') }}</p>
        @endif
    </div>
</section>

@if($plans->isNotEmpty())
<section class="vc-section vc-section--tint">
    <div class="vc-wrap vc-split">
        <div class="vc-calc" x-data="{ plans: {{ \Illuminate\Support\Js::from($calc) }}, i: 0, hours: 8, days: 5,
                fmt(v) { return {{ \Illuminate\Support\Js::from($currency->prefix ?? '') }} + v.toFixed(2).replace('.', '#').replace(/\B(?=(\d{3})+(?!\d))/g, {{ \Illuminate\Support\Js::from($thou) }}).replace('#', {{ \Illuminate\Support\Js::from($dec) }}) + {{ \Illuminate\Support\Js::from($currency->suffix ?? '') }} } }">
            <h2 class="vc-h3">{{ __('viyocloud::site.hourly.calc_title') }}</h2>
            <label>{{ __('viyocloud::site.hourly.calc_plan') }}
                <select x-model.number="i">
                    <template x-for="(p, n) in plans" :key="n"><option :value="n" x-text="p.name"></option></template>
                </select>
            </label>
            <label>{{ __('viyocloud::site.hourly.calc_hours') }} <b x-text="hours"></b>
                <input type="range" class="vc-range" min="1" max="24" x-model.number="hours">
            </label>
            <label>{{ __('viyocloud::site.hourly.calc_days') }} <b x-text="days"></b>
                <input type="range" class="vc-range" min="1" max="31" x-model.number="days">
            </label>
            <div class="vc-calc__total"><small>{{ __('viyocloud::site.hourly.calc_total') }}</small><b x-text="fmt(plans[i].rate * hours * days)"></b></div>
            <p class="vc-note">{{ __('viyocloud::site.hourly.calc_note') }}</p>
        </div>
        <div>
            <h2 class="vc-h3">{{ __('viyocloud::site.hourly.rules_title') }}</h2>
            <ul class="vc-rules">
                @foreach(__('viyocloud::site.hourly.rules') as $rule)
                <li><i class="ri-information-line"></i> {{ $rule }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
@endif
@endsection
