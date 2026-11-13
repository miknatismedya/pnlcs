<div class="vc-empty">
    <i class="ri-sparkling-2-line"></i>
    <h3>{{ $title ?? __('viyocloud::site.common.no_plans_title') }}</h3>
    <p>{{ $text ?? __('viyocloud::site.common.no_plans_text') }}</p>
    <a href="{{ url('/client/tickets/create') }}" class="vc-btn vc-btn--primary">{{ __('viyocloud::site.cta.primary') }}</a>
</div>
