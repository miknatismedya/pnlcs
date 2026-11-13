{{-- Viyo Cloud: how hourly cloud works. --}}
@php
    use Modules\Addons\ViyoCloud\Support\Catalog;
    $c = $content ?? collect();
    $val = fn ($key, $fallback) => ($c->get($key)?->content_value) ?: $fallback;
    $from = Catalog::startingHourly();
@endphp
<section class="vs-section">
    <div class="vs-wrap vs-split">
        <div>
            <span class="vs-eyebrow">{{ __('viyocloud::site.nav.hourly') }}</span>
            <h2>{{ $val('title', __('viyocloud::site.home.hourly_title')) }}</h2>
            <p class="vs-lead">{{ $val('subtitle', __('viyocloud::site.hourly.lead')) }}</p>
            @if($from)
            <p class="vs-price-line">{{ __('viyocloud::site.sec.from') }} <b>{{ $from }}</b>{{ __('viyocloud::site.common.per_hour') }}</p>
            @endif
            <a href="{{ route('viyocloud.hourly') }}" class="vs-btn vs-btn--line">{{ __('viyocloud::site.sec.hourly_cta') }} <i class="ri-arrow-right-line"></i></a>
        </div>
        <ol class="vs-steps">
            @foreach((array) __('viyocloud::site.home.hourly_steps') as $n => $step)
            <li><span>{{ $n + 1 }}</span><div><h3>{{ $step['title'] }}</h3><p>{{ $step['text'] }}</p></div></li>
            @endforeach
        </ol>
    </div>
</section>
