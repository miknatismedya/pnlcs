@extends('viyocloud::layout')
@section('title', __('viyocloud::site.ftp.title'))
@section('meta_description', __('viyocloud::site.ftp.lead'))
@section('content')
@include('viyocloud::partials.page-head', ['title' => __('viyocloud::site.ftp.title'), 'lead' => __('viyocloud::site.ftp.lead')])

@include('sections.viyo-plans', ['content' => collect(), 'line' => 'ftp', 'bare' => true])

<section class="vs-section vs-section--tint">
    <div class="vs-wrap">
        <header class="vs-head"><h2>{{ __('viyocloud::site.sec.ftp_features_title') }}</h2></header>
        <div class="vs-grid vs-grid--3">
            @foreach((array) __('viyocloud::site.ftp.features') as $f)
            <div class="vs-feature"><i class="{{ $f['icon'] }}"></i><h3>{{ $f['title'] }}</h3><p>{{ $f['text'] }}</p></div>
            @endforeach
        </div>
    </div>
</section>

@include('viyocloud::partials.faq', ['items' => __('viyocloud::site.ftp.faq')])
@endsection
