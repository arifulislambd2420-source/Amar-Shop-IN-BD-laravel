@php $current = request()->path(); @endphp
<nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-secondary border-t border-white/10 grid grid-cols-4">
    @foreach([
        ['href' => url('/'), 'match' => '/', 'label' => 'হোম'],
        ['href' => route('shop'), 'match' => 'shop', 'label' => 'শপ'],
        ['href' => route('cart.index'), 'match' => 'cart', 'label' => 'কার্ট'],
        ['href' => route('track'), 'match' => 'track', 'label' => 'ট্র্যাকিং'],
    ] as $item)
        @php $active = $current === ltrim($item['match'], '/'); @endphp
        <a href="{{ $item['href'] }}" class="relative flex flex-col items-center justify-center gap-0.5 py-2 text-[11px] {{ $active ? 'text-brand-500' : 'text-white/70' }}">
            {{ $item['label'] }}
            @if($item['match'] === 'cart')
                @livewire('cart.cart-badge')
            @endif
        </a>
    @endforeach
</nav>
