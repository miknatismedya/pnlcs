{{--
    The client area's menu (used by client/layouts/app in this theme).
    Signed in: a sidebar and a top bar. Guest: the site's own header.
    Words come from the Viyo Cloud addon and fall back to the core's own,
    so the menu still reads right while the addon is off.
--}}
@php
    $t = function (string $key, string $fallback) {
        return \Illuminate\Support\Facades\Lang::has('viyocloud::site.'.$key) ? __('viyocloud::site.'.$key) : __($fallback);
    };
    $has = fn (string $name) => \Illuminate\Support\Facades\Route::has($name);
    $user = auth()->user();
    $brand = company_name();
    $languages = \App\Models\Language::where('is_active', true)->orderBy('sort_order')->get();
    $hourlyOn = $has('viyocloud.hourly.create')
        && class_exists(\Modules\Addons\ViyoCloud\Support\HourlyBilling::class)
        && (bool) (\Modules\Addons\ViyoCloud\Support\HourlyBilling::settings()['enabled'] ?? false);

    $client = null;
    if ($user) {
        $client = session('active_client_id') ? $user->clients()->whereKey(session('active_client_id'))->first() : null;
        $client ??= $user->clients()->first();
    }
    $showBalance = $client && \App\Support\ClientPermissions::ownsActiveAccount($user);

    $logo = fn () => '<span class="vs-brand__mark"></span>'.e($brand);
@endphp

@if($user)
<aside class="vx-side" id="vxSide">
    <a href="{{ route('client.home') }}" class="vx-side__brand">
        @if(!empty($customLogo))<img src="{{ $customLogo }}" alt="{{ $brand }}">@else{!! $logo() !!}@endif
    </a>

    <a href="{{ route('client.store') }}" class="vx-side__cta {{ request()->routeIs('client.store*', 'client.cart.*') ? 'is-active' : '' }}"><i class="ri-add-line"></i> {{ $t('client.new_server', 'client.nav.order_new_service') }}</a>

    @php
        $sections = [
            [null, [
                ['client.home', 'ri-dashboard-3-line', $t('client.overview', 'client.nav.dashboard'), 'client.home'],
            ]],
            [$t('client.group_cloud', 'client.nav.services'), array_values(array_filter([
                ['client.services.index', 'ri-server-line', $t('client.servers', 'client.nav.my_services'), 'client.services.*'],
                $hourlyOn ? ['viyocloud.hourly.create', 'ri-timer-flash-line', $t('client.hourly_new', 'client.nav.order_new_service'), 'viyocloud.hourly.*'] : null,
            ]))],
            [$t('client.group_billing', 'client.nav.billing'), [
                ['client.invoices.index', 'ri-file-list-3-line', $t('client.invoices', 'client.nav.invoices'), 'client.invoices.*'],
                ['client.funds.index', 'ri-wallet-3-line', $t('client.balance', 'client.nav.add_funds'), 'client.funds.*'],
                ['client.payment-methods.index', 'ri-bank-card-line', $t('client.payment_methods', 'client.nav.payment_methods'), 'client.payment-methods.*'],
            ]],
            [$t('client.group_help', 'client.nav.support'), array_values(array_filter([
                ['client.tickets.index', 'ri-customer-service-2-line', $t('client.tickets', 'client.nav.my_tickets'), 'client.tickets.*'],
                kb_enabled() ? ['client.kb.index', 'ri-book-open-line', $t('client.kb', 'client.nav.knowledge_base'), 'client.kb.*'] : null,
                ['client.network-status', 'ri-pulse-line', $t('client.status', 'client.nav.network_status'), 'client.network-status'],
            ]))],
            [$t('client.group_account', 'client.nav.account'), array_values(array_filter([
                ['client.account.profile', 'ri-user-3-line', $t('client.profile', 'client.nav.edit_profile'), 'client.account.profile|client.account.password|client.account.contacts'],
                ['client.account.security', 'ri-shield-keyhole-line', $t('client.security', 'client.nav.security'), 'client.account.security'],
                \App\Support\ClientPermissions::ownsActiveAccount($user) ? ['client.account.users', 'ri-team-line', $t('client.users', 'client.nav.account_users'), 'client.account.users'] : null,
            ]))],
        ];
    @endphp

    <nav class="vx-side__nav">
        @foreach($sections as [$title, $items])
            @if($title)<div class="vx-side__title">{{ $title }}</div>@endif
            @foreach($items as [$route, $icon, $label, $active])
                @if($has($route))
                <a href="{{ route($route) }}" class="vx-side__link {{ request()->routeIs(...explode('|', $active)) ? 'is-active' : '' }}"><i class="{{ $icon }}"></i><span>{{ $label }}</span></a>
                @endif
            @endforeach
        @endforeach
    </nav>

    <div class="vx-side__foot">
        <a href="{{ url('/') }}" class="vx-side__link"><i class="ri-arrow-left-line"></i><span>{{ $t('client.back_to_site', 'client.nav.home_site') }}</span></a>
    </div>
</aside>
<div class="vx-scrim" onclick="document.body.classList.remove('vx-open')"></div>

<header class="vx-top">
    <button type="button" class="vx-top__burger" onclick="document.body.classList.toggle('vx-open')" aria-label="Menu"><i class="ri-menu-2-line"></i></button>
    <a href="{{ route('client.home') }}" class="vx-top__brand">{!! $logo() !!}</a>

    <div class="vx-top__right">
        @if($showBalance)
        <a href="{{ route('client.funds.index') }}" class="vx-balance" title="{{ $t('client.top_up', 'client.nav.add_funds') }}">
            <i class="ri-wallet-3-line"></i>
            <span class="vx-balance__label">{{ $t('client.balance', 'client.dashboard.account_credit') }}</span>
            <b>{{ money_fmt((float) $client->credit) }}</b>
            <span class="vx-balance__add"><i class="ri-add-line"></i></span>
        </a>
        @endif

        @if($languages->count() > 1)
        <details class="vx-menu">
            <summary class="vx-chip"><i class="ri-translate-2"></i> {{ strtoupper(app()->getLocale()) }}</summary>
            <div class="vx-menu__list">
                @foreach($languages as $language)
                <a href="{{ \App\Support\LocaleUrl::switchTo($language->code) }}" class="{{ $language->code === app()->getLocale() ? 'is-active' : '' }}">{{ $language->native_name }}</a>
                @endforeach
            </div>
        </details>
        @endif

        @if($darkModeEnabled ?? false)
        <button type="button" onclick="toggleDarkMode()" class="vx-chip" aria-label="{{ __('client.toggle_dark_mode') }}"><i class="ri-moon-line" id="darkIcon"></i><i class="ri-sun-line" id="lightIcon" style="display:none"></i></button>
        @endif

        <details class="vx-menu vx-menu--user">
            <summary class="vx-user">
                <span class="vx-user__avatar">{{ initial($user->first_name, 'U') }}</span>
                <span class="vx-user__name">{{ $user->first_name }}</span>
                <i class="ri-arrow-down-s-line"></i>
            </summary>
            <div class="vx-menu__list">
                <div class="vx-menu__head"><b>{{ trim($user->first_name.' '.$user->last_name) }}</b><small>{{ $user->email }}</small></div>
                <a href="{{ route('client.account.profile') }}"><i class="ri-user-3-line"></i> {{ $t('client.profile', 'client.nav.edit_profile') }}</a>
                <a href="{{ route('client.account.password') }}"><i class="ri-lock-password-line"></i> {{ __('client.nav.change_password') }}</a>
                @if($has('client.affiliates.index'))<a href="{{ route('client.affiliates.index') }}"><i class="ri-hand-coin-line"></i> {{ __('client.nav.affiliates') }}</a>@endif
                <form method="POST" action="{{ route('client.logout') }}">
                    @csrf
                    <button type="submit" class="is-danger"><i class="ri-logout-box-r-line"></i> {{ $t('client.sign_out', 'client.nav.sign_out') }}</button>
                </form>
            </div>
        </details>
    </div>
</header>
@else
{{-- A guest sees the site's own menu (sections/navigation in this theme). --}}
@include('sections.navigation')
@endif
