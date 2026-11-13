@php
    $contact = \Modules\Addons\ViyoCloud\Support\Site::contact();
    $brand = $brandName ?? __('viyocloud::site.brand');
    $pageTitle = trim($__env->yieldContent('title'));
    $fullTitle = $pageTitle !== '' ? $pageTitle.' · '.$brand : $brand;
    $metaDescription = trim($__env->yieldContent('meta_description')) ?: __('viyocloud::site.meta_description');
    $isLoggedIn = auth()->check();
    $products = [
        ['route' => 'viyocloud.cloud', 'icon' => 'ri-server-line', 'key' => 'cloud'],
        ['route' => 'viyocloud.hourly', 'icon' => 'ri-timer-flash-line', 'key' => 'hourly'],
        ['route' => 'viyocloud.ftp', 'icon' => 'ri-folder-cloud-line', 'key' => 'ftp'],
        ['route' => 'viyocloud.apps', 'icon' => 'ri-apps-2-line', 'key' => 'apps'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $textDirection ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fullTitle }}</title>
    @include('partials.seo-meta', ['title' => $fullTitle, 'description' => $metaDescription])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    @if(!empty($customFavicon))
    <link rel="icon" href="{{ $customFavicon }}" type="image/png">
    @endif
    @include('viyocloud::partials.styles')
    {!! hook_output('ClientAreaHeadOutput', ['user' => auth()->user()]) !!}
    @include('partials.locale-alternates')
</head>
<body class="vc" x-data="{ menu: false }">

<header class="vc-header" x-data="{ scrolled: false }" @scroll.window="scrolled = window.scrollY > 8" :class="{ 'is-scrolled': scrolled }">
    <div class="vc-wrap vc-header__row">
        <a href="{{ route('home') }}" class="vc-brand" aria-label="{{ $brand }}">
            @if(!empty($customLogo))
                <img src="{{ $customLogo }}" alt="{{ $brand }}">
            @else
                @include('viyocloud::partials.logo')
            @endif
        </a>

        <nav class="vc-nav" aria-label="{{ __('viyocloud::site.nav.menu') }}">
            <div class="vc-nav__drop" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
                <button type="button" class="vc-nav__link" @click="open = !open" :aria-expanded="open">{{ __('viyocloud::site.nav.products') }} <i class="ri-arrow-down-s-line"></i></button>
                <div class="vc-mega" x-show="open" x-transition.opacity style="display:none">
                    @foreach($products as $item)
                    <a href="{{ route($item['route']) }}" class="vc-mega__item">
                        <span class="vc-mega__icon"><i class="{{ $item['icon'] }}"></i></span>
                        <span><b>{{ __('viyocloud::site.nav.'.$item['key']) }}</b><small>{{ __('viyocloud::site.nav.'.$item['key'].'_desc') }}</small></span>
                    </a>
                    @endforeach
                </div>
            </div>
            <a href="{{ route('viyocloud.hourly') }}" class="vc-nav__link">{{ __('viyocloud::site.nav.hourly') }}</a>
            <a href="{{ route('viyocloud.apps') }}" class="vc-nav__link">{{ __('viyocloud::site.nav.apps') }}</a>
            <a href="{{ route('viyocloud.infrastructure') }}" class="vc-nav__link">{{ __('viyocloud::site.nav.infrastructure') }}</a>
            <a href="{{ url('/client/knowledgebase') }}" class="vc-nav__link">{{ __('viyocloud::site.nav.help') }}</a>
        </nav>

        <div class="vc-header__actions">
            @if(isset($activeLanguages) && $activeLanguages->count() > 1)
            <div class="vc-lang" x-data="{ open: false }" @click.away="open = false">
                <button type="button" class="vc-icon-btn" @click="open = !open" aria-label="{{ __('viyocloud::site.nav.language') }}"><i class="ri-translate-2"></i> <span>{{ strtoupper(app()->getLocale()) }}</span></button>
                <div class="vc-lang__menu" x-show="open" x-transition.opacity style="display:none">
                    @foreach($activeLanguages as $language)
                    <a href="{{ \App\Support\LocaleUrl::switchTo($language->code) }}" class="{{ $language->code === app()->getLocale() ? 'is-active' : '' }}">{{ $language->native_name }}</a>
                    @endforeach
                </div>
            </div>
            @endif
            <a href="{{ url('/client/cart') }}" class="vc-icon-btn" aria-label="{{ __('viyocloud::site.nav.cart') }}"><i class="ri-shopping-bag-3-line"></i></a>
            @if($isLoggedIn)
                <a href="{{ url('/client') }}" class="vc-btn vc-btn--dark vc-btn--sm">{{ __('viyocloud::site.nav.panel') }}</a>
            @else
                <a href="{{ route('client.login') }}" class="vc-nav__link vc-hide-sm">{{ __('viyocloud::site.nav.login') }}</a>
                <a href="{{ route('client.register') }}" class="vc-btn vc-btn--dark vc-btn--sm vc-hide-sm">{{ __('viyocloud::site.nav.signup') }}</a>
            @endif
            <button type="button" class="vc-burger" @click="menu = !menu" aria-label="{{ __('viyocloud::site.nav.menu') }}"><i class="ri-menu-4-line"></i></button>
        </div>
    </div>

    <div class="vc-mobile" x-show="menu" x-transition @click.away="menu = false" style="display:none">
        @foreach($products as $item)
        <a href="{{ route($item['route']) }}"><i class="{{ $item['icon'] }}"></i> {{ __('viyocloud::site.nav.'.$item['key']) }}</a>
        @endforeach
        <a href="{{ route('viyocloud.infrastructure') }}"><i class="ri-building-2-line"></i> {{ __('viyocloud::site.nav.infrastructure') }}</a>
        <a href="{{ url('/client/knowledgebase') }}"><i class="ri-book-open-line"></i> {{ __('viyocloud::site.nav.knowledgebase') }}</a>
        @unless($isLoggedIn)
        <a href="{{ route('client.login') }}"><i class="ri-login-circle-line"></i> {{ __('viyocloud::site.nav.login') }}</a>
        <a href="{{ route('client.register') }}" class="vc-btn vc-btn--primary">{{ __('viyocloud::site.nav.signup') }}</a>
        @endunless
    </div>
</header>

<main>
    @yield('content')
</main>

@include('viyocloud::partials.cta', ['contact' => $contact])

<footer class="vc-footer">
    <div class="vc-wrap vc-footer__grid">
        <div>
            <a href="{{ route('home') }}" class="vc-brand vc-brand--light">@include('viyocloud::partials.logo')</a>
            <p class="vc-footer__about">{{ __('viyocloud::site.footer.about') }}</p>
            @if($contact['phone'])<a href="{{ $contact['phone_href'] }}" class="vc-footer__contact"><i class="ri-phone-line"></i> {{ $contact['phone'] }}</a>@endif
            @if($contact['email'])<a href="mailto:{{ $contact['email'] }}" class="vc-footer__contact"><i class="ri-mail-line"></i> {{ $contact['email'] }}</a>@endif
            @include('partials.social-links')
        </div>
        <div>
            <h4>{{ __('viyocloud::site.footer.products') }}</h4>
            @foreach($products as $item)
            <a href="{{ route($item['route']) }}">{{ __('viyocloud::site.nav.'.$item['key']) }}</a>
            @endforeach
        </div>
        <div>
            <h4>{{ __('viyocloud::site.footer.company') }}</h4>
            <a href="{{ route('pages.about') }}">{{ __('viyocloud::site.footer.about_us') }}</a>
            <a href="{{ route('viyocloud.infrastructure') }}">{{ __('viyocloud::site.nav.infrastructure') }}</a>
            <a href="{{ url('/legal') }}">{{ __('viyocloud::site.footer.legal') }}</a>
            <a href="{{ url('/client/contact') }}">{{ __('viyocloud::site.nav.contact') }}</a>
        </div>
        <div>
            <h4>{{ __('viyocloud::site.footer.account') }}</h4>
            <a href="{{ route('client.login') }}">{{ __('viyocloud::site.nav.login') }}</a>
            <a href="{{ route('client.register') }}">{{ __('viyocloud::site.nav.signup') }}</a>
            <a href="{{ url('/client/tickets/create') }}">{{ __('viyocloud::site.nav.support') }}</a>
            <a href="{{ url('/client/knowledgebase') }}">{{ __('viyocloud::site.nav.knowledgebase') }}</a>
        </div>
    </div>
    <div class="vc-wrap vc-footer__bottom">
        <span>&copy; {{ date('Y') }} {{ $brand }}. {{ __('viyocloud::site.footer.rights') }}</span>
        <span><i class="ri-lock-2-line"></i> {{ __('viyocloud::site.footer.secure') }}</span>
    </div>
</footer>

<script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
