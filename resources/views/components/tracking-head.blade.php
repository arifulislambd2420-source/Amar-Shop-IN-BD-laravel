{{-- Storefront tracking for <head> (used by the site layout and the landing
     layout): GTM container, Meta Pixel base code, and the page_view event.
     Everything is a no-op until an ID is saved in Admin → GTM / Pixel (and
     ENABLE_GTM isn't off). Search-engine and other bots get none of it (the
     server leaves it out), and automated browsers (navigator.webdriver) skip
     it in the browser. resources/js/gtm.js provides window.trackEvent. --}}
@php
    $gtmId = \App\Support\Tracking::gtmId();
    $pixelId = \App\Support\Tracking::pixelId();
@endphp
@if($gtmId || $pixelId)
<script>
    window.dataLayer = window.dataLayer || [];
    window.__trackingBlocked = navigator.webdriver === true;
    window.__trackingOn = !window.__trackingBlocked;
    window.__pageViewId = 'pv-' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
</script>
@endif
@if($gtmId)
<script id="gtm-base">
    if (!window.__trackingBlocked) {
    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','{{ $gtmId }}');
    }
</script>
@endif
@if($pixelId)
<script id="meta-pixel-base">
    if (!window.__trackingBlocked) {
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
    n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
    document,'script','https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '{{ $pixelId }}');
    fbq('track', 'PageView', {}, { eventID: window.__pageViewId });
    }
</script>
@endif
@if($gtmId || $pixelId)
<script>
    // page_view in the dataLayer shares its event_id with the Pixel's PageView.
    if (!window.__trackingBlocked) {
        window.dataLayer.push({ ecommerce: null });
        window.dataLayer.push({ event: 'page_view', event_id: window.__pageViewId, page_location: location.href, page_title: document.title });
    }
</script>
@if(session('track_registration'))
<script>
    // A customer account was just created (flashed once by the register form).
    document.addEventListener('DOMContentLoaded', function () {
        if (window.trackEvent) {
            window.trackEvent('complete_registration', { content_name: 'Customer account' });
        }
    });
</script>
@endif
@endif
