{{-- <noscript> fallbacks, placed right after <body>: GTM iframe and Meta Pixel image. --}}
@php
    $gtmId = \App\Support\Tracking::gtmId();
    $pixelId = \App\Support\Tracking::pixelId();
@endphp
@if($gtmId)
<noscript>
    <iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}"
        height="0" width="0" style="display:none;visibility:hidden"></iframe>
</noscript>
@endif
@if($pixelId)
<noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id={{ $pixelId }}&ev=PageView&noscript=1"></noscript>
@endif
