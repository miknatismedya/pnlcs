{{-- Inner page header. $icon, $title, $lead, optional $badge. --}}
<section class="vc-phero">
    <div class="vc-wrap">
        <span class="vc-chip"><i class="{{ $icon }}"></i> {{ $badge ?? __('viyocloud::site.home.badge') }}</span>
        <h1>{{ $title }}</h1>
        <p>{{ $lead }}</p>
        @isset($from)
        <div class="vc-phero__from">{!! $from !!}</div>
        @endisset
    </div>
</section>
