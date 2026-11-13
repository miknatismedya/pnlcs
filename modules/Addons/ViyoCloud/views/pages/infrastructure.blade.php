@extends('viyocloud::layout')

@section('title', __('viyocloud::site.infra.title'))
@section('meta_description', __('viyocloud::site.infra.lead'))

@section('content')
@include('viyocloud::partials.page-hero', ['icon' => 'ri-building-2-line', 'title' => __('viyocloud::site.infra.title'), 'lead' => __('viyocloud::site.infra.lead')])

<section class="vc-section vc-section--flush">
    <div class="vc-wrap">
        <div class="vc-location">
            <div class="vc-location__pin"><i class="ri-map-pin-2-fill"></i></div>
            <div>
                <small>{{ __('viyocloud::site.infra.location_title') }}</small>
                <h2 class="vc-h3">{{ __('viyocloud::site.infra.location') }}</h2>
                <p>{{ __('viyocloud::site.infra.location_text') }}</p>
            </div>
        </div>
        <div class="vc-features">
            @foreach(__('viyocloud::site.infra.items') as $f)
            <div class="vc-feature"><i class="{{ $f['icon'] }}"></i><h3>{{ $f['title'] }}</h3><p>{{ $f['text'] }}</p></div>
            @endforeach
        </div>
    </div>
</section>
@endsection
