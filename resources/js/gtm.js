// Storefront tracking: one entry point, trackEvent(), that feeds both
// Google Tag Manager's dataLayer and (when a Pixel ID is configured, so
// window.fbq exists) the Meta Pixel. Every event gets an event_id that is
// sent to BOTH, so Meta can de-duplicate browser and server-side events.
//
//   event          dataLayer     Meta Pixel
//   page_view      page_view     PageView   (fired by the head snippet)
//   view_item      view_item     ViewContent
//   add_to_cart    add_to_cart   AddToCart
//   begin_checkout begin_checkout InitiateCheckout
//   purchase       purchase      Purchase

const FB_EVENTS = {
    view_item: 'ViewContent',
    add_to_cart: 'AddToCart',
    begin_checkout: 'InitiateCheckout',
    purchase: 'Purchase',
};

export function newEventId(prefix = 'ev') {
    const rand = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : Math.random().toString(36).slice(2) + Date.now().toString(36);
    return `${prefix}-${rand}`;
}

// Ported from the old Next.js app's src/lib/gtm-client.ts pushToDataLayer().
export function pushToDataLayer(event, data) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ event, ...data });
}

function fbParams(ecommerce = {}) {
    const items = ecommerce.items || [];
    const params = {
        currency: ecommerce.currency || 'BDT',
        value: Number(ecommerce.value || 0),
        content_type: 'product',
        content_ids: items.map((i) => String(i.item_id)),
        contents: items.map((i) => ({ id: String(i.item_id), quantity: Number(i.quantity || 1) })),
        num_items: items.reduce((n, i) => n + Number(i.quantity || 1), 0),
    };

    if (ecommerce.transaction_id) {
        params.order_id = String(ecommerce.transaction_id);
    }

    return params;
}

export function trackEvent(event, ecommerce, eventId) {
    const id = eventId || newEventId(event);

    pushToDataLayer(event, { event_id: id, ecommerce });

    if (typeof window.fbq === 'function' && FB_EVENTS[event]) {
        window.fbq('track', FB_EVENTS[event], fbParams(ecommerce), { eventID: id });
    }

    return id;
}

window.pushToDataLayer = pushToDataLayer;
window.trackEvent = trackEvent;
window.newEventId = newEventId;

// Livewire (dispatched from server-side components) -> tracking bridge.
// Each Livewire component below calls $this->dispatch('gtm:<event>', ...)
// with the ecommerce payload shape the old app used.
document.addEventListener('livewire:init', () => {
    if (!window.Livewire) return;

    window.Livewire.on('gtm:add_to_cart', (payload) => trackEvent('add_to_cart', payload.ecommerce ?? payload));
    window.Livewire.on('gtm:begin_checkout', (payload) => trackEvent('begin_checkout', payload.ecommerce ?? payload));
    window.Livewire.on('gtm:purchase', (payload) => trackEvent('purchase', payload.ecommerce ?? payload));
});
