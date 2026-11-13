{{-- Viyo Cloud: the product lines, each with its starting price. --}}
@php
    use Modules\Addons\ViyoCloud\Support\Catalog;
    $c = $content ?? collect();
    $val = fn ($key, $fallback) => ($c->get($key)?->content_value) ?: $fallback;
    $lines = [
        ['key' => 'cloud', 'route' => 'viyocloud.cloud', 'icon' => 'ri-server-line', 'from' => Catalog::startingPrice('cloud'), 'unit' => 'per_month'],
        ['key' => 'hourly', 'route' => 'viyocloud.hourly', 'icon' => 'ri-time-line', 'from' => Catalog::startingHourly(), 'unit' => 'per_hour'],
        ['key' => 'ftp', 'route' => 'viyocloud.ftp', 'icon' => 'ri-folder-line', 'from' => Catalog::startingPrice('ftp'), 'unit' => 'per_month'],
        ['key' => 'apps', 'route' => 'viyocloud.apps', 'icon' => 'ri-apps-line', 'from' => Catalog::startingPrice('apps'), 'unit' => 'per_month'],
    ];
@endphp
<section class="vs-section" id="products">
    <div class="vs-wrap">
        <header class="vs-head">
            <h2>{{ $val('title', __('viyocloud::site.home.lines_title')) }}</h2>
            <p>{{ $val('subtitle', __('viyocloud::site.home.lines_text')) }}</p>
        </header>
        <div class="vs-lines">
            @foreach($lines as $line)
            <a href="{{ route($line['route']) }}" class="vs-line">
                <i class="{{ $line['icon'] }}"></i>
                <div>
                    <h3>{{ __('viyocloud::site.nav.'.$line['key']) }}</h3>
                    <p>{{ __('viyocloud::site.home.lines.'.$line['key']) }}</p>
                </div>
                <span class="vs-line__price">
                    @if($line['from'])
                        <small>{{ __('viyocloud::site.sec.from') }}</small>
                        <b>{{ $line['from'] }}</b><small>{{ __('viyocloud::site.common.'.$line['unit']) }}</small>
                    @else
                        <small>{{ __('viyocloud::site.common.coming_soon') }}</small>
                    @endif
                </span>
            </a>
            @endforeach
        </div>
    </div>
</section>
