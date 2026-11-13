@extends('viyocloud::layout')
@section('title', __('viyocloud::site.apps.title'))
@section('meta_description', __('viyocloud::site.apps.lead'))
@section('content')
@include('viyocloud::partials.page-head', ['title' => __('viyocloud::site.apps.title'), 'lead' => __('viyocloud::site.apps.lead')])

<section class="vs-section">
    <div class="vs-wrap">
        <div class="vs-grid vs-grid--2">
            <div class="vs-feature">
                <i class="ri-server-line"></i>
                <h3>{{ __('viyocloud::site.apps.vm_title') }}</h3>
                <p>{{ __('viyocloud::site.apps.vm_text') }}</p>
                <p style="margin-top:16px"><a class="vs-btn vs-btn--line vs-btn--sm" href="{{ route('viyocloud.cloud') }}">{{ __('viyocloud::site.common.see_plans') }}</a></p>
            </div>
            <div class="vs-feature">
                <i class="ri-apps-line"></i>
                <h3>{{ __('viyocloud::site.apps.hosting_title') }}</h3>
                <p>{{ __('viyocloud::site.apps.hosting_text') }}</p>
                @if($plans->isEmpty())
                <p style="margin-top:16px"><span class="vs-tag" style="margin:0">{{ __('viyocloud::site.common.coming_soon') }}</span></p>
                @endif
            </div>
        </div>
    </div>
</section>

@if($plans->isNotEmpty())
@include('sections.viyo-plans', ['content' => collect(), 'line' => 'apps', 'bare' => true])
@endif

<section class="vs-section vs-section--tint">
    <div class="vs-wrap">
        <header class="vs-head"><h2>{{ __('viyocloud::site.sec.apps_catalog_title') }}</h2><p>{{ __('viyocloud::site.apps.catalog_note') }}</p></header>
        <div class="vs-apps">
            @foreach((array) __('viyocloud::site.apps.catalog') as $app)
            <div class="vs-app"><i class="{{ $app['icon'] }}"></i><div><b>{{ $app['name'] }}</b><small>{{ $app['text'] }}</small></div></div>
            @endforeach
        </div>
    </div>
</section>
@endsection
