{{-- GTM container script for <head> — ported from the old app's GtmScript
     component. Only renders when a gtm_id is configured (Admin → GTM /
     Pixel settings) and the ENABLE_GTM kill switch isn't off. --}}
@php($gtmId = \App\Support\Gtm::activeId())
@if($gtmId)
<script id="gtm-base">
    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','{{ $gtmId }}');
</script>
@endif
