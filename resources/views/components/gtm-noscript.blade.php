{{-- GTM <noscript> iframe, placed right after <body> — ported from the old
     app's GtmNoScript component. Same gtm_id + kill-switch gate as
     <x-gtm-script>. --}}
@php($gtmId = \App\Support\Gtm::activeId())
@if($gtmId)
<noscript>
    <iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}"
        height="0" width="0" style="display:none;visibility:hidden"></iframe>
</noscript>
@endif
