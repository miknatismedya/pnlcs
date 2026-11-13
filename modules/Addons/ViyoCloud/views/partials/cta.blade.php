<section class="vc-cta">
    <div class="vc-wrap vc-cta__box">
        <div>
            <h2>{{ __('viyocloud::site.cta.title') }}</h2>
            <p>{{ __('viyocloud::site.cta.text') }}</p>
        </div>
        <div class="vc-cta__actions">
            <a href="{{ url('/client/tickets/create') }}" class="vc-btn vc-btn--light"><i class="ri-chat-smile-3-line"></i> {{ __('viyocloud::site.cta.primary') }}</a>
            @if(!empty($contact['whatsapp']))
            <a href="{{ $contact['whatsapp'] }}" target="_blank" rel="noopener" class="vc-btn vc-btn--outline-light"><i class="ri-whatsapp-line"></i> {{ __('viyocloud::site.cta.whatsapp') }}</a>
            @endif
        </div>
    </div>
</section>
