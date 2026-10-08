{{--
    Mobile bottom navigation — a floating dark pill (styles: .mnav* in
    resources/css/app.css). The active item gets brand-coloured icon, label
    and dot, and one highlight (.mnav-glow) slides to it: from the previous
    page's slot on load, and straight away when an item is tapped. Colours
    follow the Site Setting theme (--brand / --secondary). The page keeps
    clear of the pill via `pb-mobile-nav` on the footer.
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
    class="mnav md:hidden fixed inset-x-3 z-40 h-16 rounded-[1.75rem] overflow-hidden"
    style="--n: {{ count($items) }}; --i: {{ max($activeIndex, 0) }}">
    <span class="mnav-glow absolute inset-y-0 left-0 pointer-events-none {{ $activeIndex < 0 ? 'opacity-0' : '' }}" aria-hidden="true"></span>
    <ul class="relative grid h-full" style="grid-template-columns: repeat({{ count($items) }}, minmax(0, 1fr))">
        @foreach($items as $i => $item)
            <li>
                <a href="{{ $item['href'] }}" data-index="{{ $i }}" @if($i === $activeIndex) aria-current="page" @endif
                    class="mnav-item flex h-full w-full flex-col items-center justify-center gap-[3px] text-xs font-medium leading-none">
                    <span class="relative">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $item['icon'] !!}</svg>
                        @if(! empty($item['cart']))
                            @livewire('cart.cart-badge', ['nav' => true])
                        @endif
                    </span>
                    <span class="whitespace-nowrap">{{ $item['label'] }}</span>
                    <span class="mnav-dot h-1 w-1 rounded-full bg-brand-500" aria-hidden="true"></span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
<script>
    (() => {
        const nav = document.getElementById('mnav');
        if (! nav) return;
        const glow = nav.querySelector('.mnav-glow');
        const active = Number(nav.dataset.active);
        const key = 'mnav-last';
        let last = null;
        try { last = sessionStorage.getItem(key); sessionStorage.setItem(key, String(active)); } catch (e) {}

        // Slide in from where the highlight was on the previous page. The
        // forced reflow pins the start position; the end position is set
        // synchronously, so the highlight can never be left on the old slot.
        if (active >= 0 && last !== null && Number(last) >= 0 && Number(last) !== active) {
            glow.style.transition = 'none';
            nav.style.setProperty('--i', last);
            glow.getBoundingClientRect();
            glow.style.transition = '';
            nav.style.setProperty('--i', String(active));
        }

        // Tap: move the highlight right away, before the next page loads.
        nav.addEventListener('click', (e) => {
            const link = e.target.closest('a[data-index]');
            if (! link || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey) return;
            nav.querySelector('[aria-current]')?.removeAttribute('aria-current');
            link.setAttribute('aria-current', 'page');
            glow.classList.remove('opacity-0');
            nav.style.setProperty('--i', link.dataset.index);
            try { sessionStorage.setItem(key, link.dataset.index); } catch (e) {}
        });
    })();
</script>
