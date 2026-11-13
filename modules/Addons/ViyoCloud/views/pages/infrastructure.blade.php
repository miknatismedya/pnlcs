@extends('viyocloud::layout')
@section('title', __('viyocloud::site.infra.title'))
@section('meta_description', __('viyocloud::site.infra.lead'))
@php $locations = \Modules\Addons\ViyoCloud\Support\Catalog::choices('location'); @endphp
@section('content')
@include('viyocloud::partials.page-head', ['title' => __('viyocloud::site.infra.title'), 'lead' => __('viyocloud::site.infra.lead')])

<section class="vs-section">
    <div class="vs-wrap">
        <div class="vs-grid vs-grid--3">
            @foreach((array) __('viyocloud::site.infra.items') as $item)
            <div class="vs-feature"><i class="{{ $item['icon'] }}"></i><h3>{{ $item['title'] }}</h3><p>{{ $item['text'] }}</p></div>
            @endforeach
        </div>
    </div>
</section>

@if($locations)
<section class="vs-section vs-section--tint">
    <div class="vs-wrap vs-split">
        <div>
            <h2>{{ __('viyocloud::site.sec.locations_title') }}</h2>
            <p class="vs-lead">{{ __('viyocloud::site.sec.locations_text') }}</p>
        </div>
        <ul class="vs-list">
            @foreach($locations as $loc)<li><i class="ri-map-pin-line"></i> {{ $loc }}</li>@endforeach
        </ul>
    </div>
</section>
@endif
@endsection
