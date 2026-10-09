<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $seoTitle = trim($__env->yieldContent('title')) ?: (\App\Support\SiteSettingsHelper::get('seo_title') ?: \App\Support\SiteSettingsHelper::siteName().' — '.\App\Support\SiteSettingsHelper::siteNameEn());
        $seoDescription = trim($__env->yieldContent('description')) ?: (\App\Support\SiteSettingsHelper::get('seo_description') ?: 'খাঁটি ও প্রাকৃতিক পণ্যের অনলাইন দোকান — মধু, সরিষার তেল, ঘি, খেজুর।');
        $ogImage = trim($__env->yieldContent('og_image')) ?: \App\Support\SiteSettingsHelper::get('og_image');
        $ogType = trim($__env->yieldContent('og_type')) ?: 'website';
        $canonical = trim($__env->yieldContent('canonical')) ?: \App\Support\Seo::canonical();
    @endphp
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <link rel="canonical" href="{{ $canonical }}">
    @hasSection('robots')
        <meta name="robots" content="@yield('robots')">
    @endif
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:site_name" content="{{ \App\Support\SiteSettingsHelper::siteName() }}">
    <meta property="og:locale" content="bn_BD">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $canonical }}">
    @if($ogImage)
        <meta property="og:image" content="{{ \App\Support\Media::absolute($ogImage) }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif
    @php $favicon = \App\Support\SiteSettingsHelper::get('site_favicon') ?: \App\Support\SiteSettingsHelper::get('site_logo'); @endphp
    <link rel="icon" href="{{ $favicon ?: asset('favicon.ico') }}">
    @if($favicon)
        <link rel="apple-touch-icon" href="{{ $favicon }}">
    @endif
    <meta name="theme-color" content="{{ \App\Support\SiteSettingsHelper::color('brand') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-vars')
    @include('partials.jsonld-organization')
    @stack('head')
    @livewireStyles
    <x-tracking-head />
</head>
<body class="bg-surface text-ink antialiased">
    <x-tracking-noscript />

    @include('partials.header')

    <main class="min-h-[60vh]">
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.mobile-nav')
    @include('partials.floating-buttons')

    @livewire('cart.cart-drawer')

    @livewireScripts
    @stack('scripts')
</body>
</html>
