@extends('viyocloud::layout')
@section('title', __('viyocloud::site.cloud.title'))
@section('meta_description', __('viyocloud::site.cloud.lead'))
@php
    use Modules\Addons\ViyoCloud\Support\Catalog;
    $locations = Catalog::choices('location');
    $systems = Catalog::choices('os');
@endphp
@section('content')
@include('viyocloud::partials.page-head', ['title' => __('viyocloud::site.cloud.title'), 'lead' => __('viyocloud::site.cloud.lead')])

@include('sections.viyo-plans', ['content' => collect(), 'line' => 'cloud', 'bare' => true])

<section class="vs-section vs-section--tint">
    <div class="vs-wrap vs-split">
        <div>
            <h2>{{ __('viyocloud::site.cloud.included_title') }}</h2>
            <p class="vs-lead">{{ __('viyocloud::site.sec.features_text') }}</p>
        </div>
        <ul class="vs-list">
            @foreach((array) __('viyocloud::site.cloud.included') as $item)
            <li><i class="ri-check-line"></i> {{ $item }}</li>
            @endforeach
        </ul>
    </div>
</section>

@if($locations || $systems)
<section class="vs-section">
    <div class="vs-wrap vs-split">
        @if($locations)
        <div>
            <h2>{{ __('viyocloud::site.sec.locations_title') }}</h2>
            <p class="vs-lead">{{ __('viyocloud::site.sec.locations_text') }}</p>
            <ul class="vs-list" style="margin-top:20px">
                @foreach($locations as $loc)<li><i class="ri-map-pin-line"></i> {{ $loc }}</li>@endforeach
            </ul>
        </div>
        @endif
        @if($systems)
        <div>
            <h2>{{ __('viyocloud::site.sec.systems_title') }}</h2>
            <p class="vs-lead">{{ __('viyocloud::site.sec.systems_text') }}</p>
            <ul class="vs-list" style="margin-top:20px">
                @foreach($systems as $os)<li><i class="ri-checkbox-blank-circle-line"></i> {{ $os }}</li>@endforeach
            </ul>
        </div>
        @endif
    </div>
</section>
@endif

@include('viyocloud::partials.faq', ['items' => __('viyocloud::site.cloud.faq')])
@endsection
