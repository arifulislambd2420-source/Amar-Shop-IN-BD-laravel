<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('site.name').' — '.config('site.legal_name'))</title>
    <meta name="description" content="@yield('description', 'খাঁটি ও প্রাকৃতিক পণ্যের অনলাইন দোকান — মধু, সরিষার তেল, ঘি, খেজুর।')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50 text-gray-800 antialiased pb-16 md:pb-0">

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
