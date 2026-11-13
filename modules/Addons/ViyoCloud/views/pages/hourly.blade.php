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
@include('viyocloud::partials.page-head', ['title' => __('viyocloud::site.hourly.title'), 'lead' => __('viyocloud::site.hourly.lead')])

<section class="vs-section">
    <div class="vs-wrap">
        @if($plans->isEmpty())
            <div class="vs-empty"><b>{{ __('viyocloud::site.common.no_plans_title') }}</b><p>{{ __('viyocloud::site.common.no_plans_text') }}</p></div>
        @else
            @unless($hourlyOpen)
            <div class="vs-notice"><i class="ri-information-line"></i><div><b>{{ __('viyocloud::site.hourly.soon_title') }}</b><p>{{ __('viyocloud::site.hourly.soon_text') }}</p></div></div>
            @endunless
            <div class="vs-table-wrap">
                <table class="vs-table vs-plans">
                    <thead><tr>
                        <th>{{ __('viyocloud::site.hourly.table_plan') }}</th>
                        <th>{{ __('viyocloud::site.sec.col.cpu') }}</th><th>{{ __('viyocloud::site.sec.col.ram') }}</th><th>{{ __('viyocloud::site.sec.col.disk') }}</th>
                        <th class="is-num">{{ __('viyocloud::site.hourly.table_hourly') }}</th>
                        <th class="is-num">{{ __('viyocloud::site.hourly.table_month') }}</th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                    @foreach($plans as $plan)
                    <tr class="{{ $plan['featured'] ? 'is-featured' : '' }}">
                        <td><b>{{ $plan['name'] }}</b></td>
                        <td data-label="{{ __('viyocloud::site.sec.col.cpu') }}">{{ __('viyocloud::site.sec.unit.cpu', ['n' => $plan['cpu']]) }}</td>
                        <td data-label="{{ __('viyocloud::site.sec.col.ram') }}">{{ __('viyocloud::site.sec.unit.ram', ['n' => $plan['ram']]) }}</td>
                        <td data-label="{{ __('viyocloud::site.sec.col.disk') }}">{{ __('viyocloud::site.sec.unit.disk', ['n' => $plan['disk']]) }}</td>
                        <td class="is-num" data-label="{{ __('viyocloud::site.hourly.table_hourly') }}"><b>{{ $plan['hourly'] }}</b></td>
                        <td class="is-num" data-label="{{ __('viyocloud::site.hourly.table_month') }}">{{ $plan['hourly_month'] }}</td>
                        <td class="is-action">
                            @if($hourlyOpen)
                            <a href="{{ route('viyocloud.hourly.create', ['plan' => $plan['slug']]) }}" class="vs-btn vs-btn--line vs-btn--sm">{{ __('viyocloud::site.hourly.start') }}</a>
                            @else
                            <span class="vs-btn vs-btn--line vs-btn--sm is-disabled">{{ __('viyocloud::site.common.coming_soon') }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="vs-note">{{ __('viyocloud::site.common.vat_note') }}</p>
        @endif
    </div>
</section>

@if($plans->isNotEmpty())
<section class="vs-section vs-section--tint">
    <div class="vs-wrap vs-split">
        <div class="vs-calc" x-data="{ plans: {{ \Illuminate\Support\Js::from($calc) }}, i: 0, hours: 8, days: 5,
                fmt(v) { return {{ \Illuminate\Support\Js::from($currency->prefix ?? '') }} + v.toFixed(2).replace('.', '#').replace(/\B(?=(\d{3})+(?!\d))/g, {{ \Illuminate\Support\Js::from($thou) }}).replace('#', {{ \Illuminate\Support\Js::from($dec) }}) + {{ \Illuminate\Support\Js::from($currency->suffix ?? '') }} } }">
            <h2 style="font-size:20px">{{ __('viyocloud::site.hourly.calc_title') }}</h2>
            <label>{{ __('viyocloud::site.hourly.calc_plan') }}
                <select x-model.number="i"><template x-for="(p, n) in plans" :key="n"><option :value="n" x-text="p.name"></option></template></select>
            </label>
            <label>{{ __('viyocloud::site.hourly.calc_hours') }} <b x-text="hours"></b><input type="range" min="1" max="24" x-model.number="hours"></label>
            <label>{{ __('viyocloud::site.hourly.calc_days') }} <b x-text="days"></b><input type="range" min="1" max="31" x-model.number="days"></label>
            <div class="vs-calc__total"><span>{{ __('viyocloud::site.hourly.calc_total') }}</span><b x-text="fmt(plans[i].rate * hours * days)"></b></div>
            <p class="vs-note" style="margin:0">{{ __('viyocloud::site.hourly.calc_note') }}</p>
        </div>
        <div>
            <h2>{{ __('viyocloud::site.hourly.rules_title') }}</h2>
            <ul class="vs-list" style="margin-top:20px">
                @foreach((array) __('viyocloud::site.hourly.rules') as $rule)<li><i class="ri-information-line"></i> {{ $rule }}</li>@endforeach
            </ul>
        </div>
    </div>
</section>
@endif

@endsection
