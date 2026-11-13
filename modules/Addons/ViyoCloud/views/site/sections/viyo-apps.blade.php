{{-- Viyo Cloud: the apps that can come installed. --}}
@php
    $c = $content ?? collect();
    $val = fn ($key, $fallback) => ($c->get($key)?->content_value) ?: $fallback;
@endphp
<section class="vs-section vs-section--tint">
    <div class="vs-wrap">
        <header class="vs-head">
            <h2>{{ $val('title', __('viyocloud::site.home.apps_title')) }}</h2>
            <p>{{ $val('subtitle', __('viyocloud::site.home.apps_text')) }}</p>
        </header>
        <div class="vs-apps">
            @foreach((array) __('viyocloud::site.apps.catalog') as $app)
            <div class="vs-app"><i class="{{ $app['icon'] }}"></i><div><b>{{ $app['name'] }}</b><small>{{ $app['text'] }}</small></div></div>
            @endforeach
        </div>
        <p class="vs-more"><a href="{{ route('viyocloud.apps') }}">{{ __('viyocloud::site.home.apps_all') }} <i class="ri-arrow-right-line"></i></a></p>
    </div>
</section>
