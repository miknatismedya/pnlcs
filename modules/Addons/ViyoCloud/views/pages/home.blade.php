@extends('viyocloud::layout')

@section('title', __('viyocloud::site.home.title'))

@php
    use Modules\Addons\ViyoCloud\Support\Catalog;

    $plans = $plans ?? Catalog::plans('cloud');
    $picker = $plans->map(fn ($p) => [
        'name' => $p['name'],
        'specs' => array_values(array_filter([
            $p['cpu'] ? __('viyocloud::site.common.spec.cpu', ['n' => $p['cpu']]) : null,
            $p['ram'] ? __('viyocloud::site.common.spec.ram', ['n' => $p['ram']]) : null,
            $p['disk'] ? __('viyocloud::site.common.spec.disk', ['n' => $p['disk']]) : null,
        ])),
        'cpu' => (int) $p['cpu'], 'ram' => (int) $p['ram'], 'disk' => (int) $p['disk'],
        'monthly' => $p['prices']['monthly']['per_month'] ?? null,
        'hourly' => $p['hourly'],
        'url' => $p['order_url'],
    ])->values();
    $maxCpu = max(1, (int) $plans->max('cpu'));
    $maxRam = max(1, (int) $plans->max('ram'));
    $maxDisk = max(1, (int) $plans->max('disk'));
    $startIndex = max(0, (int) $plans->search(fn ($p) => $p['featured']));

    $lines = [
        ['key' => 'cloud', 'route' => 'viyocloud.cloud', 'icon' => 'ri-server-line', 'from' => Catalog::startingPrice('cloud'), 'unit' => 'per_month'],
        ['key' => 'hourly', 'route' => 'viyocloud.hourly', 'icon' => 'ri-timer-flash-line', 'from' => Catalog::startingHourly(), 'unit' => 'per_hour'],
        ['key' => 'ftp', 'route' => 'viyocloud.ftp', 'icon' => 'ri-folder-cloud-line', 'from' => Catalog::startingPrice('ftp'), 'unit' => 'per_month'],
        ['key' => 'apps', 'route' => 'viyocloud.apps', 'icon' => 'ri-apps-2-line', 'from' => Catalog::startingPrice('apps'), 'unit' => 'per_month'],
    ];
@endphp

@section('content')
{{-- ===== Hero ===== --}}
<section class="vc-hero">
    <div class="vc-hero__glow" aria-hidden="true"></div>
    <div class="vc-wrap vc-hero__grid">
        <div class="vc-hero__copy">
            <span class="vc-chip vc-chip--glass"><span class="vc-pulse"></span> {{ __('viyocloud::site.home.badge') }}</span>
            <h1>{!! __('viyocloud::site.home.headline', ['em' => '<em>'.e(__('viyocloud::site.home.headline_em')).'</em>']) !!}</h1>
            <p class="vc-hero__lead">{{ __('viyocloud::site.home.lead') }}</p>
            <div class="vc-hero__cta">
                <a href="{{ route('viyocloud.cloud') }}" class="vc-btn vc-btn--primary vc-btn--lg">{{ __('viyocloud::site.home.cta_primary') }} <i class="ri-arrow-right-line"></i></a>
                <a href="{{ route('viyocloud.hourly') }}" class="vc-btn vc-btn--outline-light vc-btn--lg"><i class="ri-timer-flash-line"></i> {{ __('viyocloud::site.home.cta_secondary') }}</a>
            </div>
            <ul class="vc-hero__trust">
                @foreach(__('viyocloud::site.home.trust') as $item)
                <li><i class="ri-check-double-line"></i> {{ $item }}</li>
                @endforeach
            </ul>
        </div>

        @if($picker->isNotEmpty())
        <div class="vc-picker" x-data="{ i: {{ $startIndex }}, plans: {{ \Illuminate\Support\Js::from($picker) }}, get p() { return this.plans[this.i] } }">
            <div class="vc-picker__head">
                <span>{{ __('viyocloud::site.home.picker_title') }}</span>
                <b x-text="p.name"></b>
            </div>
            <div class="vc-picker__bars">
                <div><label>vCPU <em x-text="p.cpu"></em></label><div class="vc-bar"><i :style="`width:${p.cpu / {{ $maxCpu }} * 100}%`"></i></div></div>
                <div><label>RAM <em x-text="p.ram + ' GB'"></em></label><div class="vc-bar"><i :style="`width:${p.ram / {{ $maxRam }} * 100}%`"></i></div></div>
                <div><label>NVMe <em x-text="p.disk + ' GB'"></em></label><div class="vc-bar"><i :style="`width:${p.disk / {{ $maxDisk }} * 100}%`"></i></div></div>
            </div>
            <input type="range" class="vc-range" min="0" :max="plans.length - 1" step="1" x-model.number="i" aria-label="{{ __('viyocloud::site.home.picker_hint') }}">
            <div class="vc-picker__hint"><i class="ri-drag-move-line"></i> {{ __('viyocloud::site.home.picker_hint') }}</div>
            <div class="vc-picker__prices">
                <div x-show="p.monthly"><small>{{ __('viyocloud::site.home.picker_monthly') }}</small><b x-text="p.monthly"></b><span>{{ __('viyocloud::site.common.per_month') }}</span></div>
                <div x-show="p.hourly"><small>{{ __('viyocloud::site.home.picker_hourly') }}</small><b x-text="p.hourly"></b><span>{{ __('viyocloud::site.common.per_hour') }}</span></div>
            </div>
            <a :href="p.url" class="vc-btn vc-btn--primary vc-btn--block"><span x-text="@js(__('viyocloud::site.home.picker_order', ['plan' => '__P__'])).replace('__P__', p.name)"></span> <i class="ri-arrow-right-up-line"></i></a>
        </div>
        @endif
    </div>
</section>

{{-- ===== Product lines ===== --}}
<section class="vc-section">
    <div class="vc-wrap">
        <div class="vc-head">
            <h2 class="vc-h2">{{ __('viyocloud::site.home.lines_title') }}</h2>
            <p>{{ __('viyocloud::site.home.lines_text') }}</p>
        </div>
        <div class="vc-bento">
            @foreach($lines as $n => $line)
            <a href="{{ route($line['route']) }}" class="vc-tile vc-tile--{{ $line['key'] }}">
                <span class="vc-tile__icon"><i class="{{ $line['icon'] }}"></i></span>
                <h3>{{ __('viyocloud::site.nav.'.$line['key']) }}</h3>
                <p>{{ __('viyocloud::site.home.lines.'.$line['key']) }}</p>
                <span class="vc-tile__foot">
                    @if($line['from'])
                        {{ __('viyocloud::site.common.from', ['price' => $line['from']]) }}{{ __('viyocloud::site.common.'.$line['unit']) }}
                    @else
                        {{ __('viyocloud::site.common.learn_more') }}
                    @endif
                    <i class="ri-arrow-right-line"></i>
                </span>
            </a>
            @endforeach
        </div>
    </div>
</section>

{{-- ===== Features ===== --}}
<section class="vc-section vc-section--tint">
    <div class="vc-wrap">
        <div class="vc-head"><h2 class="vc-h2">{{ __('viyocloud::site.home.features_title') }}</h2></div>
        <div class="vc-features">
            @foreach(__('viyocloud::site.home.features') as $f)
            <div class="vc-feature"><i class="{{ $f['icon'] }}"></i><h3>{{ $f['title'] }}</h3><p>{{ $f['text'] }}</p></div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===== Hourly in three steps ===== --}}
<section class="vc-section">
    <div class="vc-wrap vc-split">
        <div>
            <span class="vc-chip"><i class="ri-timer-flash-line"></i> {{ __('viyocloud::site.nav.hourly') }}</span>
            <h2 class="vc-h2">{{ __('viyocloud::site.home.hourly_title') }}</h2>
            <a href="{{ route('viyocloud.hourly') }}" class="vc-link">{{ __('viyocloud::site.common.learn_more') }} <i class="ri-arrow-right-line"></i></a>
        </div>
        <ol class="vc-steps">
            @foreach(__('viyocloud::site.home.hourly_steps') as $n => $step)
            <li><span>{{ $n + 1 }}</span><div><h3>{{ $step['title'] }}</h3><p>{{ $step['text'] }}</p></div></li>
            @endforeach
        </ol>
    </div>
</section>

{{-- ===== Apps ===== --}}
<section class="vc-section vc-section--dark">
    <div class="vc-wrap">
        <div class="vc-head vc-head--light">
            <h2 class="vc-h2">{{ __('viyocloud::site.home.apps_title') }}</h2>
            <p>{{ __('viyocloud::site.home.apps_text') }}</p>
        </div>
        <div class="vc-apps">
            @foreach(__('viyocloud::site.apps.catalog') as $app)
            <div class="vc-app"><i class="{{ $app['icon'] }}"></i><b>{{ $app['name'] }}</b></div>
            @endforeach
        </div>
        <div class="vc-center"><a href="{{ route('viyocloud.apps') }}" class="vc-btn vc-btn--outline-light">{{ __('viyocloud::site.home.apps_all') }} <i class="ri-arrow-right-line"></i></a></div>
    </div>
</section>

@include('viyocloud::partials.faq', ['items' => __('viyocloud::site.cloud.faq')])
@endsection
