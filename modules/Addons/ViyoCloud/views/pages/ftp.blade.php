@extends('viyocloud::layout')

@section('title', __('viyocloud::site.ftp.title'))
@section('meta_description', __('viyocloud::site.ftp.lead'))

@section('content')
@include('viyocloud::partials.page-hero', ['icon' => 'ri-folder-cloud-line', 'title' => __('viyocloud::site.ftp.title'), 'lead' => __('viyocloud::site.ftp.lead')])

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
        <div class="vc-features">
            @foreach(__('viyocloud::site.ftp.features') as $f)
            <div class="vc-feature"><i class="{{ $f['icon'] }}"></i><h3>{{ $f['title'] }}</h3><p>{{ $f['text'] }}</p></div>
            @endforeach
        </div>
    </div>
</section>

@include('viyocloud::partials.faq', ['items' => __('viyocloud::site.ftp.faq')])
@endsection
