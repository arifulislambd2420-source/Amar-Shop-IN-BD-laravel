// Storefront tracking: one entry point, trackEvent(), that feeds both
// Google Tag Manager's dataLayer and (when a Pixel ID is configured, so
// window.fbq exists) the Meta Pixel. Every event gets an event_id that is
// sent to BOTH, so Meta can de-duplicate browser and server-side events.
//
//   event                  dataLayer              Meta Pixel
//   page_view              page_view              PageView         (fired by the head snippet)
//   view_item              view_item              ViewContent      (again when a colour / size is picked)
//   add_to_cart            add_to_cart            AddToCart        (also for "order now")
//   begin_checkout         begin_checkout         InitiateCheckout (once per cart)
//   purchase               purchase               Purchase
//   search                 search                 Search
//   view_category          view_category          ViewCategory     (custom)
//   complete_registration  complete_registration  CompleteRegistration
//   time_on_site           time_on_site           TimeOnSite       (custom; seconds = 10 / 30 / 60 / 120)
//   scroll_depth           scroll_depth           ScrollDepth      (custom; percent = 25 / 50 / 75 / 100)
//   <admin's name>         <admin's name>         OrderPlaced      (custom unless it is a standard Meta event)
//
// Before every dataLayer event the old `ecommerce` object is cleared (ecommerce: null), so one
// event's items never leak into the next in GTM. Nothing is sent when the head snippet did not
// switch tracking on (no IDs, a bot, or an automated browser).

// [fbq method, Meta event name]
const FB_EVENTS = {
    view_item: ['track', 'ViewContent'],
    add_to_cart: ['track', 'AddToCart'],
    begin_checkout: ['track', 'InitiateCheckout'],
    purchase: ['track', 'Purchase'],
    search: ['track', 'Search'],
    complete_registration: ['track', 'CompleteRegistration'],
    view_category: ['trackCustom', 'ViewCategory'],
    time_on_site: ['trackCustom', 'TimeOnSite'],
    scroll_depth: ['trackCustom', 'ScrollDepth'],
};

// Events whose payload is the ecommerce object (the others carry plain parameters).
const ECOMMERCE_EVENTS = ['view_item', 'add_to_cart', 'begin_checkout', 'purchase'];

// Names Meta already knows: sent with fbq('track'), anything else with fbq('trackCustom').
const META_STANDARD = [
    'PageView', 'ViewContent', 'Search', 'AddToCart', 'AddToWishlist', 'InitiateCheckout', 'AddPaymentInfo', 'Purchase',
    'Lead', 'CompleteRegistration', 'Contact', 'CustomizeProduct', 'Donate', 'FindLocation', 'Schedule', 'StartTrial',
    'SubmitApplication', 'Subscribe',
];

const blocked = () => window.__trackingBlocked === true;

export function newEventId(prefix = 'ev') {
    const rand = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : Math.random().toString(36).slice(2) + Date.now().toString(36);
    return `${prefix}-${rand}`;
}

// Ported from the old Next.js app's src/lib/gtm-client.ts pushToDataLayer(),
// plus the GA4-recommended `ecommerce: null` before each event.
export function pushToDataLayer(event, data) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ ecommerce: null });
    window.dataLayer.push({ event, ...data });
}

function fbParams(event, data = {}) {
    if (ECOMMERCE_EVENTS.includes(event)) {
        const items = data.items || [];
        const params = {
            currency: data.currency || 'BDT',
            value: Number(data.value || 0),
            content_type: 'product',
            content_ids: items.map((i) => String(i.item_id)),
            contents: items.map((i) => ({ id: String(i.item_id), quantity: Number(i.quantity || 1) })),
            num_items: items.reduce((n, i) => n + Number(i.quantity || 1), 0),
        };

        if (data.content_name) params.content_name = String(data.content_name);
        if (data.content_category) params.content_category = String(data.content_category);
        if (data.transaction_id) params.order_id = String(data.transaction_id);

        return params;
    }

    switch (event) {
        case 'search':
            return { search_string: String(data.search_term || ''), content_type: 'product' };
        case 'view_category':
            return { content_category: String(data.category_name || ''), content_ids: (data.content_ids || []).map(String), content_type: 'product' };
        case 'complete_registration':
            return { content_name: String(data.content_name || 'Customer account'), status: true };
        case 'time_on_site':
            return { seconds: Number(data.seconds || 0) };
        case 'scroll_depth':
            return { percent: Number(data.percent || 0) };
        default:
            return {};
    }
}

export function trackEvent(event, data, eventId) {
    if (blocked()) return null;

    const id = eventId || newEventId(event);

    pushToDataLayer(event, ECOMMERCE_EVENTS.includes(event) ? { event_id: id, ecommerce: data } : { event_id: id, ...data });

    if (typeof window.fbq === 'function' && FB_EVENTS[event]) {
        const [method, name] = FB_EVENTS[event];
        window.fbq(method, name, fbParams(event, data), { eventID: id });
    }

    return id;
}

// A named event with the order's figures — the "order placed" event, whose name the admin chooses.
export function trackNamedEvent(name, ecommerce, eventId) {
    if (blocked() || !name) return null;

    const id = eventId || newEventId(name);

    pushToDataLayer(name, { event_id: id, ecommerce });

    if (typeof window.fbq === 'function') {
        window.fbq(META_STANDARD.includes(name) ? 'track' : 'trackCustom', name, fbParams('purchase', ecommerce), { eventID: id });
    }

    return id;
}

window.pushToDataLayer = pushToDataLayer;
window.trackEvent = trackEvent;
window.trackNamedEvent = trackNamedEvent;
window.newEventId = newEventId;

// Interest: time spent on the page (only while the tab is open AND visible) and how far down it was
// scrolled. Each step is sent once per page. Works on every page, landing pages included.
const TIME_STEPS = [10, 30, 60, 120];
const SCROLL_STEPS = [25, 50, 75, 100];

export function startInterestTracking() {
    if (window.__interestStarted || blocked() || !window.__trackingOn) return;
    window.__interestStarted = true;

    let seconds = 0;
    let nextTime = 0;
    const timer = setInterval(() => {
        if (document.visibilityState !== 'visible') return;

        seconds += 1;

        while (nextTime < TIME_STEPS.length && seconds >= TIME_STEPS[nextTime]) {
            trackEvent('time_on_site', { seconds: TIME_STEPS[nextTime] });
            nextTime += 1;
        }

        if (nextTime >= TIME_STEPS.length) clearInterval(timer);
    }, 1000);

    let nextScroll = 0;
    const onScroll = () => {
        const page = Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0);
        const seen = ((window.scrollY || window.pageYOffset) + window.innerHeight) / Math.max(page, 1) * 100;

        // 100% means "reached the bottom" (a pixel of rounding is allowed).
        while (nextScroll < SCROLL_STEPS.length && seen >= (SCROLL_STEPS[nextScroll] === 100 ? 99 : SCROLL_STEPS[nextScroll])) {
            trackEvent('scroll_depth', { percent: SCROLL_STEPS[nextScroll] });
            nextScroll += 1;
        }

        if (nextScroll >= SCROLL_STEPS.length) window.removeEventListener('scroll', onScroll);
    };

    window.addEventListener('scroll', onScroll, { passive: true });
}

document.addEventListener('DOMContentLoaded', startInterestTracking);

// Livewire (dispatched from server-side components) -> tracking bridge.
// Each Livewire component below calls $this->dispatch('gtm:<event>', ...)
// with the ecommerce payload shape the old app used.
document.addEventListener('livewire:init', () => {
    if (!window.Livewire) return;

    window.Livewire.on('gtm:add_to_cart', (payload) => trackEvent('add_to_cart', payload.ecommerce ?? payload));
    window.Livewire.on('gtm:begin_checkout', (payload) => trackEvent('begin_checkout', payload.ecommerce ?? payload));
    window.Livewire.on('gtm:purchase', (payload) => trackEvent('purchase', payload.ecommerce ?? payload));
    // A colour / size was picked on the product page: ViewContent again, with that choice's price and name.
    window.Livewire.on('gtm:view_item', (payload) => trackEvent('view_item', payload.ecommerce ?? payload));
    // The order was just placed: the admin-named event (OrderPlaced by default).
    window.Livewire.on('gtm:order_placed', (payload) => trackNamedEvent(payload.name, payload.ecommerce, payload.event_id));
});
