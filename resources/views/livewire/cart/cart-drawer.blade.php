<div x-data="{ open: false }" @open-cart-drawer.window="open = true" x-cloak>
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-black/60 z-50" @click="open = false" style="display: none;"></div>

    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
        class="fixed top-0 right-0 h-full w-full max-w-sm bg-white z-50 shadow-2xl flex flex-col" style="display: none;">

        <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="font-bold text-lg">আপনার কার্ট</h2>
            <button type="button" @click="open = false" aria-label="বন্ধ করুন" class="text-gray-500 hover:text-ink">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-4">
            @forelse($lines as $line)
                <div class="flex gap-3" wire:key="drawer-line-{{ $line['key'] }}">
                    <div class="h-16 w-16 rounded-lg bg-surface overflow-hidden shrink-0">
                        @if($line['image'])
                            <img src="{{ $line['image'] }}" alt="{{ $line['name'] }}" width="64" height="64" loading="lazy" class="h-full w-full object-cover">
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium line-clamp-2">{{ $line['name'] }}</p>
                        <p class="text-brand-500 text-sm font-semibold">@taka($line['price'])</p>
                        <div class="flex items-center gap-2 mt-1">
                            <button type="button" class="h-6 w-6 rounded border border-gray-300 text-sm" wire:click="updateQuantity({{ $line['product']->id }}, {{ $line['variant']?->id ?? 'null' }}, {{ $line['quantity'] - 1 }})">−</button>
                            <span class="text-sm w-5 text-center">{{ $line['quantity'] }}</span>
                            <button type="button" class="h-6 w-6 rounded border border-gray-300 text-sm" wire:click="updateQuantity({{ $line['product']->id }}, {{ $line['variant']?->id ?? 'null' }}, {{ $line['quantity'] + 1 }})">+</button>
                            <button type="button" class="ml-auto text-xs text-error-500" wire:click="remove({{ $line['product']->id }}, {{ $line['variant']?->id ?? 'null' }})">সরান</button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-center text-gray-400 py-10">আপনার কার্ট খালি</p>
            @endforelse
        </div>

        @if(count($lines) > 0)
            <div class="p-4 border-t border-gray-200 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">সাবটোটাল</span>
                    <span class="font-semibold">@taka($subtotal)</span>
                </div>
                <a href="{{ route('checkout') }}" class="block text-center bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-lg">চেকআউট করুন</a>
                <a href="{{ route('cart.index') }}" class="block text-center text-sm text-gray-600 hover:text-brand-500">কার্ট পেজ দেখুন</a>
            </div>
        @endif
    </div>
</div>
