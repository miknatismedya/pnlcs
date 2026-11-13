@extends('viyocloud::layout')

@section('title', __('viyocloud::site.apps.title'))
@section('meta_description', __('viyocloud::site.apps.lead'))

@section('content')
@include('viyocloud::partials.page-hero', ['icon' => 'ri-apps-2-line', 'title' => __('viyocloud::site.apps.title'), 'lead' => __('viyocloud::site.apps.lead')])

<section class="vc-section vc-section--flush">
    <div class="vc-wrap">
        <div class="vc-head vc-head--left">
            <h2 class="vc-h2"><i class="ri-server-line"></i> {{ __('viyocloud::site.apps.vm_title') }}</h2>
            <p>{{ __('viyocloud::site.apps.vm_text') }}</p>
        </div>
        <div class="vc-apps vc-apps--light">
            @foreach(__('viyocloud::site.apps.catalog') as $app)
            <div class="vc-app"><i class="{{ $app['icon'] }}"></i><b>{{ $app['name'] }}</b><small>{{ $app['text'] }}</small></div>
            @endforeach
        </div>
        <p class="vc-note">{{ __('viyocloud::site.apps.catalog_note') }}</p>
        <div class="vc-center"><a href="{{ route('viyocloud.cloud') }}" class="vc-btn vc-btn--primary">{{ __('viyocloud::site.common.see_plans') }} <i class="ri-arrow-right-line"></i></a></div>
    </div>
</section>

<section class="vc-section vc-section--tint">
    <div class="vc-wrap">
        <div class="vc-head vc-head--left">
            <h2 class="vc-h2"><i class="ri-stack-line"></i> {{ __('viyocloud::site.apps.hosting_title') }}</h2>
            <p>{{ __('viyocloud::site.apps.hosting_text') }}</p>
        </div>
        @if($plans->isEmpty())
            @include('viyocloud::partials.empty', ['title' => __('viyocloud::site.common.coming_soon')])
        @else
            @include('viyocloud::partials.plan-grid', ['plans' => $plans])
        @endif
    </div>
</section>
@endsection
