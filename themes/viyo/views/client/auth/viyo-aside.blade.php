{{-- The brand panel beside the sign-in forms. --}}
@php
    $has = fn (string $key) => \Illuminate\Support\Facades\Lang::has('viyocloud::site.'.$key);
    $points = $has('auth.points') ? (array) __('viyocloud::site.auth.points') : [];
    $contact = class_exists(\Modules\Addons\ViyoCloud\Support\Site::class) ? \Modules\Addons\ViyoCloud\Support\Site::contact() : [];
@endphp
<aside class="vx-auth__aside">
    <a href="{{ url('/') }}" class="vx-auth__brand">
        <span class="vs-brand__mark"></span>{{ company_name() }}
    </a>
    <div class="vx-auth__pitch">
        @if($has('auth.title'))
        <h2>{{ __('viyocloud::site.auth.title') }}</h2>
        <p>{{ __('viyocloud::site.auth.lead') }}</p>
        @endif
        @if($points)
        <ul>
            @foreach($points as $point)<li><i class="ri-check-line"></i> {{ $point }}</li>@endforeach
        </ul>
        @endif
    </div>
    @if(!empty($contact['phone']) || !empty($contact['email']))
    <div class="vx-auth__help">
        {{ $has('auth.help') ? __('viyocloud::site.auth.help') : '' }}
        @if(!empty($contact['phone']))<a href="{{ $contact['phone_href'] }}"><i class="ri-phone-line"></i> {{ $contact['phone'] }}</a>@endif
        @if(!empty($contact['email']))<a href="mailto:{{ $contact['email'] }}"><i class="ri-mail-line"></i> {{ $contact['email'] }}</a>@endif
    </div>
    @endif
</aside>
