{{-- Landing page events (the Purchase event comes from the order confirmation
     page): ViewContent on load, InitiateCheckout the first time the visitor
     touches the order form. Skipped entirely when tracking is off. --}}
@if (\App\Support\Tracking::enabled() && isset($landingPage) && $landingPage->product)
    @php
        $packagePrices = collect((array) $landingPage->packages)->pluck('price')->filter(fn ($p) => is_numeric($p))->map(fn ($p) => (float) $p);
        $price = $packagePrices->isNotEmpty() ? $packagePrices->min() : $landingPage->effectivePrice();
        $payload = \App\Support\Tracking::product($landingPage->product, (float) $price);
    @endphp
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.trackEvent) return;

            window.trackEvent('view_item', @json($payload));

            var started = false;
            document.addEventListener('focusin', function (e) {
                if (started || !e.target.closest || !e.target.closest('#order-form, #lp-order-form')) return;
                started = true;
                window.trackEvent('begin_checkout', @json($payload));
            });
        });
    </script>
@endif
