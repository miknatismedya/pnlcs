@extends('viyocloud::layout')

@section('title', __('viyocloud::site.cloud.title'))
@section('meta_description', __('viyocloud::site.cloud.lead'))

@section('content')
@include('viyocloud::partials.page-hero', ['icon' => 'ri-server-line', 'title' => __('viyocloud::site.cloud.title'), 'lead' => __('viyocloud::site.cloud.lead')])

<section class="vc-section vc-section--flush">
    <div class="vc-wrap">
        @if($plans->isEmpty())
            @include('viyocloud::partials.empty')
        @else
            @include('viyocloud::partials.plan-grid', ['plans' => $plans])
        @endif
    </div>
</section>

<section class="vc-section vc-section--tint">
    <div class="vc-wrap">
        <div class="vc-head"><h2 class="vc-h2">{{ __('viyocloud::site.cloud.included_title') }}</h2></div>
        <ul class="vc-checks">
            @foreach(__('viyocloud::site.cloud.included') as $item)
            <li><i class="ri-checkbox-circle-fill"></i> {{ $item }}</li>
            @endforeach
        </ul>
    </div>
</section>

@include('viyocloud::partials.faq', ['items' => __('viyocloud::site.cloud.faq')])
@endsection
