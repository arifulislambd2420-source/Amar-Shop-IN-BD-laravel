{{-- Standalone error page (no Vite, no database): shown for 500/503/403/419/429,
     i.e. exactly when the normal layout might itself be what's failing.
     Expects $code, $title, $message. Colors fall back if settings can't be read. --}}
@php
    try {
        $brand = \App\Support\SiteSettingsHelper::color('brand');
        $siteName = \App\Support\SiteSettingsHelper::siteName();
    } catch (\Throwable) {
        $brand = '#f97316';
        $siteName = config('site.name');
    }
    $digits = strtr((string) $code, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
@endphp
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} — {{ $siteName }}</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;background:#f9fafb;color:#1f2937;font-family:system-ui,-apple-system,'Segoe UI','Noto Sans Bengali','Hind Siliguri',sans-serif;text-align:center}
        .code{font-size:4.5rem;font-weight:800;color:{{ $brand }};line-height:1}
        h1{margin:1rem 0 .5rem;font-size:1.5rem}
        p{margin:0;color:#4b5563}
        .actions{margin-top:2rem;display:flex;flex-wrap:wrap;gap:.75rem;justify-content:center}
        a{display:inline-block;padding:.75rem 1.5rem;border-radius:.5rem;font-weight:600;text-decoration:none}
        .primary{background:{{ $brand }};color:#fff}
        .secondary{background:#fff;color:#1f2937;border:1px solid #d1d5db}
    </style>
</head>
<body>
    <main>
        <div class="code">{{ $digits }}</div>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <div class="actions">
            <a class="primary" href="{{ url('/') }}">হোমে যান</a>
            <a class="secondary" href="{{ url('/shop') }}">শপে যান</a>
        </div>
    </main>
</body>
</html>
