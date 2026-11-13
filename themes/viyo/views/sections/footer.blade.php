{{-- Viyo Cloud footer. The description is editable in Setup -> Appearance (footer). --}}
@php
    $c = $content ?? collect();
    $val = fn ($key, $fallback) => ($c->get($key)?->content_value) ?: $fallback;
    $has = fn (string $r) => \Illuminate\Support\Facades\Route::has($r);
    $contact = class_exists(\Modules\Addons\ViyoCloud\Support\Site::class) ? \Modules\Addons\ViyoCloud\Support\Site::contact() : [];
    $brand = $brandName ?? company_name();
@endphp
<footer class="vs-footer">
    <div class="vs-wrap vs-footer__grid">
        <div class="vs-footer__about">
            <a href="{{ route('home') }}" class="vs-brand"><span class="vs-brand__mark"></span>{{ $brand }}</a>
            <p>{{ $val('description', __('viyocloud::site.footer.about')) }}</p>
            @if(!empty($contact['phone']))<a href="{{ $contact['phone_href'] }}"><i class="ri-phone-line"></i> {{ $contact['phone'] }}</a>@endif
            @if(!empty($contact['email']))<a href="mailto:{{ $contact['email'] }}"><i class="ri-mail-line"></i> {{ $contact['email'] }}</a>@endif
            @include('partials.social-links')
        </div>
        <div>
            <h4>{{ __('viyocloud::site.footer.products') }}</h4>
            @foreach(['cloud', 'hourly', 'ftp', 'apps'] as $key)
                @if($has('viyocloud.'.$key))<a href="{{ route('viyocloud.'.$key) }}">{{ __('viyocloud::site.nav.'.$key) }}</a>@endif
            @endforeach
        </div>
        <div>
            <h4>{{ __('viyocloud::site.footer.company') }}</h4>
            <a href="{{ route('pages.about') }}">{{ __('viyocloud::site.footer.about_us') }}</a>
            @if($has('viyocloud.infrastructure'))<a href="{{ route('viyocloud.infrastructure') }}">{{ __('viyocloud::site.nav.infrastructure') }}</a>@endif
            <a href="{{ route('client.announcements.index') }}">{{ __('client.nav.announcements') }}</a>
            <a href="{{ url('/legal') }}">{{ __('viyocloud::site.footer.legal') }}</a>
        </div>
        <div>
            <h4>{{ __('viyocloud::site.footer.account') }}</h4>
            <a href="{{ route('client.login') }}">{{ __('viyocloud::site.nav.login') }}</a>
            <a href="{{ route('client.register') }}">{{ __('viyocloud::site.nav.signup') }}</a>
            <a href="{{ route('client.tickets.create') }}">{{ __('viyocloud::site.nav.support') }}</a>
            @if(kb_enabled())<a href="{{ route('client.kb.index') }}">{{ __('viyocloud::site.nav.knowledgebase') }}</a>@endif
            <a href="{{ route('client.contact') }}">{{ __('viyocloud::site.nav.contact') }}</a>
        </div>
    </div>
    <div class="vs-wrap vs-footer__bottom">
        <span>&copy; {{ date('Y') }} {{ $brandCopyright ?? $brand }}. {{ __('viyocloud::site.footer.rights') }}</span>
        <span>{{ __('viyocloud::site.footer.secure') }}</span>
    </div>
</footer>
