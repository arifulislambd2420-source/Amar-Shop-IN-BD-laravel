<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $seoTitle = trim($__env->yieldContent('title')) ?: ($landingPage->seo_title ?: $landingPage->headline);
        $seoDescription = trim($__env->yieldContent('description')) ?: ($landingPage->seo_description ?: \App\Support\Seo::description($landingPage->sub_headline ?: $landingPage->description));
        $ogImage = $landingPage->og_image ?: ($landingPage->hero_image ?: \App\Support\SiteSettingsHelper::get('og_image'));
        $canonical = url()->current();
    @endphp
    <title>{{ $seoTitle }}</title>
    @if($seoDescription)<meta name="description" content="{{ $seoDescription }}">@endif
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $seoTitle }}">
    @if($seoDescription)<meta property="og:description" content="{{ $seoDescription }}">@endif
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:locale" content="bn_BD">
    @if($ogImage)
        <meta property="og:image" content="{{ \App\Support\Media::absolute($ogImage) }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif
    {{-- Landing pages are a standalone conversion funnel: no site header,
         footer or cart — just this page's own content and order form. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-vars')
    @livewireStyles
    <x-tracking-head />
</head>
<body class="antialiased">
    <x-tracking-noscript />

    @yield('content')

    @livewireScripts
    @include('landing._tracking')
    @stack('scripts')
</body>
</html>
