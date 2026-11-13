{{-- The client area's footer, in place of the core one. --}}
@php
    $t = fn (string $key, string $fallback) => \Illuminate\Support\Facades\Lang::has('viyocloud::site.'.$key) ? __('viyocloud::site.'.$key) : __($fallback);
@endphp
<footer class="vx-foot">
    <span>&copy; {{ date('Y') }} {{ company_name() }}</span>
    <nav>
        <a href="{{ url('/') }}">{{ $t('client.back_to_site', 'client.nav.home_site') }}</a>
        <a href="{{ route('client.contact') }}">{{ __('client.nav.contact') }}</a>
        <a href="{{ route('client.announcements.index') }}">{{ __('client.nav.announcements') }}</a>
        @if(kb_enabled())<a href="{{ route('client.kb.index') }}">{{ __('client.nav.knowledge_base') }}</a>@endif
        <a href="{{ url('/legal') }}">{{ $t('footer.legal', 'client.nav.contact') }}</a>
    </nav>
</footer>
<!--vx:end-->
