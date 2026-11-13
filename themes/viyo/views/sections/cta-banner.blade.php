{{-- Viyo Cloud call to action. Wording from Setup -> Appearance; empty = translated default. --}}
@php
    $c = $content ?? collect();
    $val = fn ($key, $fallback) => ($c->get($key)?->content_value) ?: $fallback;
    $contact = class_exists(\Modules\Addons\ViyoCloud\Support\Site::class) ? \Modules\Addons\ViyoCloud\Support\Site::contact() : [];
@endphp
<section class="vs-section vs-section--tight">
    <div class="vs-wrap">
        <div class="vs-cta">
            <div>
                <h2>{{ $val('title', __('viyocloud::site.cta.title')) }}</h2>
                <p>{{ $val('subtitle', __('viyocloud::site.cta.text')) }}</p>
            </div>
            <div class="vs-actions">
                <a href="{{ $val('cta_url', url('/client/tickets/create')) }}" class="vs-btn vs-btn--primary">{{ $val('cta_text', __('viyocloud::site.cta.primary')) }}</a>
                @if(!empty($contact['whatsapp']))
                <a href="https://wa.me/{{ $contact['whatsapp'] }}" class="vs-btn vs-btn--line" rel="noopener"><i class="ri-whatsapp-line"></i> {{ __('viyocloud::site.cta.whatsapp') }}</a>
                @endif
                @if(!empty($contact['phone']))
                <a href="{{ $contact['phone_href'] }}" class="vs-btn vs-btn--line"><i class="ri-phone-line"></i> {{ $contact['phone'] }}</a>
                @endif
            </div>
        </div>
    </div>
</section>
