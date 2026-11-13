{{-- Viyo Cloud: the site menu. Product pages come from the Viyo Cloud addon. --}}
@php
    $has = fn (string $r) => \Illuminate\Support\Facades\Route::has($r);
    $links = array_values(array_filter([
        $has('viyocloud.cloud') ? [route('viyocloud.cloud'), __('viyocloud::site.nav.cloud'), 'viyocloud.cloud'] : null,
        $has('viyocloud.hourly') ? [route('viyocloud.hourly'), __('viyocloud::site.nav.hourly'), 'viyocloud.hourly'] : null,
        $has('viyocloud.ftp') ? [route('viyocloud.ftp'), __('viyocloud::site.nav.ftp'), 'viyocloud.ftp'] : null,
        $has('viyocloud.apps') ? [route('viyocloud.apps'), __('viyocloud::site.nav.apps'), 'viyocloud.apps'] : null,
        $has('viyocloud.infrastructure') ? [route('viyocloud.infrastructure'), __('viyocloud::site.nav.infrastructure'), 'viyocloud.infrastructure'] : null,
        kb_enabled() ? [route('client.kb.index'), __('viyocloud::site.nav.help'), 'client.kb.*'] : null,
    ]));
    $brand = $brandName ?? company_name();
    $languages = $activeLanguages ?? \App\Models\Language::where('is_active', true)->orderBy('sort_order')->get();
@endphp
<header class="vs-header">
    <div class="vs-wrap vs-header__row">
        <a href="{{ route('home') }}" class="vs-brand">
            @if(!empty($customLogo))<img src="{{ $customLogo }}" alt="{{ $brand }}">@else<span class="vs-brand__mark"></span>{{ $brand }}@endif
        </a>
        <nav class="vs-nav">
            @foreach($links as [$url, $label, $route])
            <a href="{{ $url }}" class="{{ request()->routeIs($route) ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <div class="vs-header__actions">
            @if($languages->count() > 1)
            <details class="vs-drop">
                <summary>{{ strtoupper(app()->getLocale()) }} <i class="ri-arrow-down-s-line"></i></summary>
                <div class="vs-drop__menu">
                    @foreach($languages as $language)
                    <a href="{{ \App\Support\LocaleUrl::switchTo($language->code) }}" class="{{ $language->code === app()->getLocale() ? 'is-active' : '' }}">{{ $language->native_name }}</a>
                    @endforeach
                </div>
            </details>
            @endif
            <a href="{{ route('client.cart.index') }}" class="vs-icon" aria-label="{{ __('viyocloud::site.nav.cart') }}"><i class="ri-shopping-cart-line"></i></a>
            @auth
                <a href="{{ route('client.home') }}" class="vs-btn vs-btn--primary vs-btn--sm">{{ __('viyocloud::site.nav.panel') }}</a>
            @else
                <a href="{{ route('client.login') }}" class="vs-header__login">{{ __('viyocloud::site.nav.login') }}</a>
                <a href="{{ route('client.register') }}" class="vs-btn vs-btn--primary vs-btn--sm">{{ __('viyocloud::site.nav.signup') }}</a>
            @endauth
            <details class="vs-burger">
                <summary aria-label="{{ __('viyocloud::site.nav.menu') }}"><i class="ri-menu-line"></i></summary>
                <div class="vs-burger__menu">
                    @foreach($links as [$url, $label])
                    <a href="{{ $url }}">{{ $label }}</a>
                    @endforeach
                    @guest
                    <a href="{{ route('client.login') }}">{{ __('viyocloud::site.nav.login') }}</a>
                    @endguest
                </div>
            </details>
        </div>
    </div>
</header>
