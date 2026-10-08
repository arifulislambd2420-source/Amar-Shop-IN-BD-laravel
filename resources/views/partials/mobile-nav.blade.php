{{--
    Mobile bottom navigation — "floating active circle with notch" (styles:
    .mnav* in resources/css/app.css). A light bar with a round notch cut into
    its top edge; the active item's icon sits in a brand-coloured circle
    resting in that notch, its label stays in the bar. Circle, notch and icon
    are all placed from --i (the active slot), which the browser animates, so
    they glide together: from the previous page's slot on load, and right
    away when an item is tapped. Colours follow the Site Setting brand colour.
    The page keeps clear of the bar via `pb-mobile-nav` on the footer.
--}}
@php
    $items = [
        ['href' => url('/'), 'is' => ['/'], 'label' => 'হোম',
            'icon' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/>'],
        ['href' => route('shop'), 'is' => ['shop', 'shop/*', 'product/*', 'offers'], 'label' => 'শপ',
            'icon' => '<path d="M6 7h12l1 13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1L6 7z"/><path d="M9 7V6a3 3 0 0 1 6 0v1"/>'],
        ['href' => route('cart.index'), 'is' => ['cart', 'checkout', 'checkout/*'], 'label' => 'কার্ট', 'cart' => true,
            'icon' => '<circle cx="9" cy="20" r="1.25"/><circle cx="18" cy="20" r="1.25"/><path d="M2 3h3l2.6 12.1a1.5 1.5 0 0 0 1.5 1.2h8.7a1.5 1.5 0 0 0 1.5-1.2L21 7H6.2"/>'],
        ['href' => route('track'), 'is' => ['track', 'track/*', 'order/*'], 'label' => 'ট্র্যাকিং',
            'icon' => '<path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7"/><circle cx="7" cy="18" r="1.75"/><circle cx="17" cy="18" r="1.75"/>'],
        ['href' => auth()->check() ? route('customer.account') : route('customer.login'), 'is' => ['customer', 'customer/*'], 'label' => 'অ্যাকাউন্ট',
            'icon' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'],
    ];
    $activeIndex = collect($items)->search(fn ($item) => request()->is(...$item['is']));
    $activeIndex = $activeIndex === false ? -1 : $activeIndex;
@endphp

<nav id="mnav" aria-label="মোবাইল মেনু" data-active="{{ $activeIndex }}"
    class="mnav md:hidden fixed inset-x-3 z-40 {{ $activeIndex < 0 ? 'mnav--none' : '' }}"
    style="--n: {{ count($items) }}; --i: {{ max($activeIndex, 0) }}">
    <span class="mnav-bar" aria-hidden="true"></span>
    <span class="mnav-bubble pointer-events-none" aria-hidden="true"></span>
    <ul class="relative grid h-full" style="padding-inline: var(--mnav-pad); grid-template-columns: repeat({{ count($items) }}, minmax(0, 1fr))">
        @foreach($items as $i => $item)
            <li>
                <a href="{{ $item['href'] }}" data-index="{{ $i }}" @if($i === $activeIndex) aria-current="page" @endif
                    class="mnav-item relative block h-full w-full text-xs font-medium leading-none">
                    <span class="mnav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $item['icon'] !!}</svg>
                        @if(! empty($item['cart']))
                            @livewire('cart.cart-badge', ['nav' => true])
                        @endif
                    </span>
                    <span class="mnav-label">{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
<script>
    (() => {
        const nav = document.getElementById('mnav');
        if (! nav) return;
        const active = Number(nav.dataset.active);
        const key = 'mnav-last';
        let last = null;
        try { last = sessionStorage.getItem(key); sessionStorage.setItem(key, String(active)); } catch (e) {}

        // Glide in from the previous page's slot. The forced reflow pins the
        // start; the end is set synchronously, so the circle can never be left
        // on the old slot even if the animation does not run.
        if (active >= 0 && last !== null && Number(last) >= 0 && Number(last) !== active) {
            nav.style.transition = 'none';
            nav.style.setProperty('--i', last);
            nav.getBoundingClientRect();
            nav.style.transition = '';
            nav.style.setProperty('--i', String(active));
        }

        // Phone keyboard up (a text field has focus) -> hide the nav and the
        // floating buttons so they don't cover the field (CSS: html.kb-open).
        const typing = (el) => el && el.matches && el.matches('input:not([type=checkbox]):not([type=radio]):not([type=submit]):not([type=button]):not([type=file]), textarea, select, [contenteditable=""], [contenteditable=true]');
        document.addEventListener('focusin', (e) => { if (typing(e.target)) document.documentElement.classList.add('kb-open'); });
        document.addEventListener('focusout', () => setTimeout(() => {
            if (! typing(document.activeElement)) document.documentElement.classList.remove('kb-open');
        }, 50));

        // Tap: move the circle and notch right away, before the next page loads.
        nav.addEventListener('click', (e) => {
            const link = e.target.closest('a[data-index]');
            if (! link || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey) return;
            nav.querySelector('[aria-current]')?.removeAttribute('aria-current');
            link.setAttribute('aria-current', 'page');
            nav.classList.remove('mnav--none');
            nav.style.setProperty('--i', link.dataset.index);
            try { sessionStorage.setItem(key, link.dataset.index); } catch (e) {}
        });
    })();
</script>
