{{-- Viyo Cloud: what every server comes with. --}}
@php
    $c = $content ?? collect();
    $val = fn ($key, $fallback) => ($c->get($key)?->content_value) ?: $fallback;
@endphp
<section class="vs-section vs-section--tint">
    <div class="vs-wrap">
        <header class="vs-head">
            <h2>{{ $val('title', __('viyocloud::site.home.features_title')) }}</h2>
            <p>{{ $val('subtitle', __('viyocloud::site.sec.features_text')) }}</p>
        </header>
        <div class="vs-grid vs-grid--3">
            @foreach((array) __('viyocloud::site.home.features') as $f)
            <div class="vs-feature">
                <i class="{{ $f['icon'] }}"></i>
                <h3>{{ $f['title'] }}</h3>
                <p>{{ $f['text'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>
