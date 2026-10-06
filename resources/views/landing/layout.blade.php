<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $landingPage->headline)</title>
    <meta name="description" content="@yield('description', $landingPage->sub_headline)">
    {{-- Landing pages are a standalone conversion funnel: no site header,
         footer or cart — just this page's own content and order form. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-vars')
    @livewireStyles
</head>
<body class="antialiased">
    @yield('content')

    @livewireScripts
</body>
</html>
