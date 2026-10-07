<div class="max-w-5xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">আপনার কার্ট</h1>

    @if(count($lines) === 0)
        <div class="text-center py-16">
            <p class="text-gray-500 mb-4">আপনার কার্টটি এখন খালি।</p>
            <a href="{{ route('shop') }}" class="text-brand-500 font-semibold">শপিং শুরু করুন →</a>
        </div>
    @else
        <div class="grid md:grid-cols-3 gap-8">
            <div class="md:col-span-2 divide-y divide-gray-200 border border-gray-200 rounded-xl overflow-hidden">
                @foreach($lines as $line)
                    <div class="flex gap-4 p-4" wire:key="cart-line-{{ $line['key'] }}">
                        <div class="h-20 w-20 rounded-lg bg-surface overflow-hidden shrink-0">
                            @if($line['image'])
                                <img src="{{ $line['image'] }}" alt="{{ $line['name'] }}" width="80" height="80" loading="lazy" class="h-full w-full object-cover">
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium">{{ $line['name'] }}</p>
                            <p class="text-brand-500 font-semibold text-sm mt-1">@taka($line['price'])</p>
                            <div class="flex items-center gap-3 mt-2">
                                <div class="flex items-center border border-gray-300 rounded-lg">
                                    <button type="button" class="h-8 w-8 text-sm" wire:click="updateQuantity({{ $line['product']->id }}, {{ $line['variant']?->id ?? 'null' }}, {{ $line['quantity'] - 1 }})">−</button>
                                    <span class="w-8 text-center text-sm">{{ $line['quantity'] }}</span>
                                    <button type="button" class="h-8 w-8 text-sm" wire:click="updateQuantity({{ $line['product']->id }}, {{ $line['variant']?->id ?? 'null' }}, {{ $line['quantity'] + 1 }})">+</button>
                                </div>
                                <button type="button" class="text-xs text-error-500" wire:click="remove({{ $line['product']->id }}, {{ $line['variant']?->id ?? 'null' }})">সরান</button>
                            </div>
                        </div>
                        <div class="text-right font-semibold">@taka($line['line_total'])</div>
                    </div>
                @endforeach
            </div>

            <div class="border border-gray-200 rounded-xl p-5 h-fit">
                <div class="font-semibold mb-3">অর্ডার সামারি</div>
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-gray-600">সাবটোটাল</span>
                    <span class="font-semibold">@taka($subtotal)</span>
                </div>
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-gray-600">ডেলিভারি চার্জ</span>
                    @if(is_null($shippingFee))
                        <span class="text-xs text-gray-500">চেকআউটে জেলা অনুযায়ী</span>
                    @elseif($shippingFee == 0)
                        <span class="font-semibold text-success-600">ফ্রি</span>
                    @else
                        <span class="font-semibold">@taka($shippingFee)</span>
                    @endif
                </div>
                @if(is_null($shippingFee))
                    <p class="text-xs text-gray-500 mb-2">ঢাকার ভেতরে @taka(\App\Support\Delivery::dhakaFee()), ঢাকার বাইরে @taka(\App\Support\Delivery::outsideFee())</p>
                @endif
                @if($freeRemaining)
                    <p class="text-xs text-success-600 mb-2">আর @taka($freeRemaining) এর কেনাকাটা করলে ডেলিভারি ফ্রি!</p>
                @endif
                <div class="flex justify-between text-lg font-bold border-t border-gray-200 pt-3 mb-4">
                    <span>মোট</span>
                    <span class="text-brand-500">@taka($subtotal + ($shippingFee ?? 0))</span>
                </div>
                <a href="{{ route('checkout') }}" class="block text-center bg-brand-500 hover:bg-brand-600 text-white font-semibold py-3 rounded-lg">চেকআউট করুন</a>
            </div>
        </div>
    @endif
</div>
