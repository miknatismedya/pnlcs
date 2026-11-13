{{--
    Viyo Cloud hero. Wording from Setup -> Appearance (badge_text, title,
    description, cta_text, cta_url); an empty field shows the translated default.
--}}
@php
    use Modules\Addons\ViyoCloud\Support\Catalog;
    $c = $content ?? collect();
    $val = fn ($key, $fallback) => ($c->get($key)?->content_value) ?: $fallback;
    $catalog = class_exists(Catalog::class);
    $has = fn (string $r) => \Illuminate\Support\Facades\Route::has($r);
    $prices = $catalog ? array_values(array_filter([
        ['cloud', 'viyocloud.cloud', Catalog::startingPrice('cloud'), 'per_month'],
        ['hourly', 'viyocloud.hourly', Catalog::startingHourly(), 'per_hour'],
        ['ftp', 'viyocloud.ftp', Catalog::startingPrice('ftp'), 'per_month'],
    ], fn ($row) => $row[2] && $has($row[1]))) : [];
@endphp
<section class="vs-hero">
    <div class="vs-wrap vs-hero__grid">
        <div>
            <span class="vs-eyebrow">{{ $val('badge_text', __('viyocloud::site.home.badge')) }}</span>
            <h1>{{ inline_markup($val('title', __('viyocloud::site.sec.hero_title'))) }}</h1>
            <p class="vs-lead">{{ $val('description', __('viyocloud::site.home.lead')) }}</p>
            <div class="vs-actions">
                <a href="{{ $val('cta_url', $has('viyocloud.cloud') ? route('viyocloud.cloud') : route('client.store')) }}" class="vs-btn vs-btn--primary">{{ $val('cta_text', __('viyocloud::site.home.cta_primary')) }}</a>
                @if($has('viyocloud.hourly'))
                <a href="{{ route('viyocloud.hourly') }}" class="vs-btn vs-btn--line">{{ __('viyocloud::site.home.cta_secondary') }}</a>
                @endif
            </div>
            <ul class="vs-checks">
                @foreach((array) __('viyocloud::site.home.trust') as $item)
                <li><i class="ri-check-line"></i> {{ $item }}</li>
                @endforeach
            </ul>
        </div>
        @if($prices)
        <aside class="vs-pricebox">
            <h2>{{ __('viyocloud::site.sec.pricebox_title') }}</h2>
            <ul>
                @foreach($prices as [$key, $route, $price, $unit])
                <li>
                    <a href="{{ route($route) }}">
                        <span>{{ __('viyocloud::site.nav.'.$key) }}</span>
                        <b>{{ $price }}<small>{{ __('viyocloud::site.common.'.$unit) }}</small></b>
                    </a>
                </li>
                @endforeach
            </ul>
            <p>{{ __('viyocloud::site.common.vat_note') }}</p>
        </aside>
        @endif
    </div>
</section>
