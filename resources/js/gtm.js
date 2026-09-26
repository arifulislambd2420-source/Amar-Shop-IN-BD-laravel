// Ported from the old Next.js app's src/lib/gtm-client.ts pushToDataLayer().
export function pushToDataLayer(event, data) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ event, ...data });
}

window.pushToDataLayer = pushToDataLayer;

// Livewire (dispatched from server-side components) -> dataLayer bridge.
// Each Livewire component below calls $this->dispatch('gtm:<event>', ...)
// with the exact ecommerce payload shape the old app used.
document.addEventListener('livewire:init', () => {
    if (!window.Livewire) return;

    window.Livewire.on('gtm:add_to_cart', (payload) => pushToDataLayer('add_to_cart', payload));
    window.Livewire.on('gtm:begin_checkout', (payload) => pushToDataLayer('begin_checkout', payload));
    window.Livewire.on('gtm:purchase', (payload) => pushToDataLayer('purchase', payload));
});
