<div x-data="{ open: false }" @open-cart-drawer.window="open = true" x-cloak>
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-black/60 z-50" @click="open = false" style="display: none;"></div>

    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
        class="fixed top-0 right-0 h-full w-full max-w-sm bg-white z-50 shadow-2xl flex flex-col" style="display: none;">

        <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="font-bold text-lg">আপনার কার্ট</h2>
            <button type="button" @click="open = false" aria-label="বন্ধ করুন" class="-mr-2 inline-flex h-11 w-11 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-ink">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-4">
            @forelse($lines as $line)
                @php
                    $pid = $line['product']->id;
                    $vid = $line['variant']?->id ?? 'null';
                    $out = $line['quantity'] < 1;
                @endphp
                <div class="flex gap-3 border-b border-gray-100 py-3 first:pt-0 last:border-0" wire:key="drawer-line-{{ $line['key'] }}">
                    <div class="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-surface">
                        @if($line['image'])
                            <img src="{{ $line['image'] }}" alt="" width="64" height="64" loading="lazy" @class(['h-full w-full object-cover', 'opacity-50 grayscale' => $out])>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="line-clamp-2 text-sm font-medium">{{ $line['product']->name }}</p>
                        <p class="text-xs text-gray-500">@if($line['variant'])সাইজ: {{ $line['variant']->label }} · @endif<span class="font-semibold text-brand-600">@taka($line['price'])</span></p>
                        @if($out)<p class="text-xs font-medium text-error-500">স্টক শেষ</p>@endif
                        <div class="mt-1.5 flex items-center justify-between gap-2">
                            @unless($out)
                                <div class="inline-flex items-center overflow-hidden rounded-xl border border-gray-300" role="group" aria-label="পরিমাণ">
                                    <button type="button" class="inline-flex h-11 w-11 items-center justify-center text-lg hover:bg-gray-50" aria-label="পরিমাণ কমান" wire:click="updateQuantity({{ $pid }}, {{ $vid }}, {{ $line['quantity'] - 1 }})">−</button>
                                    <span class="w-8 text-center text-sm font-semibold tabular-nums">{{ $line['quantity'] }}</span>
                                    <button type="button" class="inline-flex h-11 w-11 items-center justify-center text-lg hover:bg-gray-50 disabled:text-gray-300" aria-label="পরিমাণ বাড়ান" @disabled($line['quantity'] >= $line['stock']) wire:click="updateQuantity({{ $pid }}, {{ $vid }}, {{ $line['quantity'] + 1 }})">+</button>
                                </div>
                            @else
                                <span></span>
                            @endunless
                            <button type="button" class="inline-flex h-11 items-center rounded-xl px-3 text-sm font-medium text-gray-500 hover:bg-error-50 hover:text-error-500" wire:click="remove({{ $pid }}, {{ $vid }})">সরান</button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="py-10 text-center text-gray-500">আপনার কার্ট খালি</p>
            @endforelse
        </div>

        @if(count($lines) > 0)
            <div class="p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] border-t border-gray-200 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">সাবটোটাল</span>
                    <span class="font-semibold">@taka($subtotal)</span>
                </div>
                <a href="{{ route('checkout') }}" class="flex h-12 items-center justify-center rounded-xl bg-brand-500 font-semibold text-white hover:bg-brand-600">চেকআউট করুন</a>
                <a href="{{ route('cart.index') }}" class="flex min-h-11 items-center justify-center text-sm text-gray-600 hover:text-brand-500">কার্ট পেজ দেখুন</a>
            </div>
        @endif
    </div>
</div>
