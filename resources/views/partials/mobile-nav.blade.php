{{--
    Mobile bottom navigation. 64px bar (+ iPhone safe area), icon above a 12px
    label, each item a full-height tap target. The active item is marked by
    colour, a tinted pill behind the icon and a bar on top — so it stays
    visible whatever brand colour is set in Site Setting. The page itself
    keeps clear of this bar via `pb-mobile-nav` on <body>.
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
    ];
@endphp
<nav aria-label="মোবাইল মেনু"
    class="md:hidden fixed inset-x-0 bottom-0 z-40 bg-white border-t border-gray-200 shadow-[0_-2px_10px_rgba(0,0,0,0.06)] pb-safe">
    <ul class="grid grid-cols-4 h-16">
        @foreach($items as $item)
            @php $active = request()->is(...$item['is']); @endphp
            <li>
                <a href="{{ $item['href'] }}" @if($active) aria-current="page" @endif
                    @class([
                        'relative flex h-full w-full flex-col items-center justify-center gap-1 text-xs leading-none transition-colors',
                        'text-brand-600 font-semibold' => $active,
                        'text-gray-500 font-medium active:bg-gray-50' => ! $active,
                    ])>
                    @if($active)
                        <span class="absolute top-0 left-1/2 -translate-x-1/2 h-[3px] w-10 rounded-b-full bg-brand-500" aria-hidden="true"></span>
                    @endif
                    <span @class(['relative flex h-8 w-14 items-center justify-center rounded-full', 'bg-brand-50' => $active])>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active ? '2.2' : '1.8' }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $item['icon'] !!}</svg>
                        @if(! empty($item['cart']))
                            @livewire('cart.cart-badge', ['nav' => true])
                        @endif
                    </span>
                    <span>{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
