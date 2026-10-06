<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $seoTitle = trim($__env->yieldContent('title')) ?: (\App\Support\SiteSettingsHelper::get('seo_title') ?: \App\Support\SiteSettingsHelper::siteName().' — '.\App\Support\SiteSettingsHelper::siteNameEn());
        $seoDescription = trim($__env->yieldContent('description')) ?: (\App\Support\SiteSettingsHelper::get('seo_description') ?: 'খাঁটি ও প্রাকৃতিক পণ্যের অনলাইন দোকান — মধু, সরিষার তেল, ঘি, খেজুর।');
        $ogImage = \App\Support\SiteSettingsHelper::get('og_image');
    @endphp
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-vars')
    @livewireStyles
    <x-gtm-script />
</head>
<body class="bg-surface text-ink antialiased pb-16 md:pb-0">
    <x-gtm-noscript />

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
