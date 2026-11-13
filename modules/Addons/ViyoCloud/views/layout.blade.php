{{--
    Viyo Cloud product pages. The menu, call to action and footer are the
    homepage's own sections (sections.navigation, sections.cta-banner,
    sections.footer), so they are edited in one place: Setup -> Appearance.
--}}
@php
    $brand = $brandName ?? company_name();
    $pageTitle = trim($__env->yieldContent('title'));
    $fullTitle = $pageTitle !== '' ? $pageTitle.' · '.$brand : $brand;
    $metaDescription = trim($__env->yieldContent('meta_description')) ?: __('viyocloud::site.meta_description');
    $sectionContent = \App\Models\HomepageContent::whereIn('section_slug', ['cta-banner', 'footer'])->get()->groupBy('section_slug')->map(fn ($rows) => $rows->keyBy('content_key'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $textDirection ?? 'ltr' }}" data-theme="{{ request()->cookie('pnlcs_theme') === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fullTitle }}</title>
    @include('partials.seo-meta', ['title' => $fullTitle, 'description' => $metaDescription])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    @if(!empty($customFavicon))<link rel="icon" href="{{ $customFavicon }}" type="image/png">@endif
    @if(!empty($themeCssVars))<style id="theme-vars">{!! $themeCssVars !!}</style>@endif
    @if(!empty($activeThemeAssets))<link rel="stylesheet" href="{{ $activeThemeAssets }}/css/theme.css">@endif
    {!! hook_output('ClientAreaHeadOutput', ['user' => auth()->user()]) !!}
    @include('partials.locale-alternates')
</head>
<body class="vs">
    @include('sections.navigation')
    <main>
        @yield('content')
    </main>
    @include('sections.cta-banner', ['content' => $sectionContent['cta-banner'] ?? collect()])
    @include('sections.footer', ['content' => $sectionContent['footer'] ?? collect()])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    {!! hook_output('ClientAreaFooterOutput', ['user' => auth()->user()]) !!}
</body>
</html>
