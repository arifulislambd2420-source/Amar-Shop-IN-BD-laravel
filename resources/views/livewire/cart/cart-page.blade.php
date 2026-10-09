@php
    $unavailable = array_filter($lines, fn ($l) => $l['quantity'] < 1);
    $count = array_sum(array_column($lines, 'quantity'));
    $total = $subtotal + ($shippingFee ?? 0);
    $step = 'inline-flex h-11 w-11 items-center justify-center text-xl text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:text-gray-300 disabled:hover:bg-transparent';
@endphp
<div class="max-w-6xl mx-auto px-4 py-5 md:py-8">
    <h1 class="mb-4 text-xl font-bold text-gray-900 md:mb-6 md:text-2xl">আপনার কার্ট @if($count)<span class="text-base font-normal text-gray-500">({{ $count }}টি পণ্য)</span>@endif</h1>

    @if(count($lines) === 0)
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-brand-50 text-brand-500">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.25"/><circle cx="18" cy="20" r="1.25"/><path d="M2 3h3l2.6 12.1a1.5 1.5 0 0 0 1.5 1.2h8.7a1.5 1.5 0 0 0 1.5-1.2L21 7H6.2"/></svg>
            </div>
            <p class="text-lg font-semibold text-gray-800">আপনার কার্টটি এখন খালি</p>
            <p class="mt-1 text-sm text-gray-500">পছন্দের পণ্য কার্টে যোগ করুন, তারপর এখান থেকে অর্ডার করুন।</p>
            <a href="{{ route('shop') }}" class="mt-6 inline-flex h-11 items-center rounded-xl bg-brand-500 px-6 text-sm font-semibold text-white hover:bg-brand-600">কেনাকাটা শুরু করুন</a>
        </div>
    @else
        <div class="grid gap-5 lg:grid-cols-[1fr_360px] lg:gap-8">
            <div class="space-y-3">
                @if($unavailable)
                    <p class="rounded-2xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-500" role="alert">
                        {{ count($unavailable) }}টি পণ্য এখন স্টকে নেই — এগুলো অর্ডারে যাবে না। সরিয়ে দিন অথবা পরে আবার দেখুন।
                    </p>
                @endif

                <ul class="divide-y divide-gray-100 rounded-2xl border border-gray-200 bg-white">
                    @foreach($lines as $line)
                        @php
                            $pid = $line['product']->id;
                            $vid = $line['variant']?->id ?? 'null';
                            $out = $line['quantity'] < 1;
                            $atMax = $line['quantity'] >= $line['stock'];
                        @endphp
                        <li class="flex gap-3 p-3 sm:gap-4 sm:p-4" wire:key="cart-line-{{ $line['key'] }}">
                            <a href="{{ route('product.show', $line['product']->slug) }}" class="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-surface sm:h-24 sm:w-24">
                                @if($line['image'])
                                    <img src="{{ $line['image'] }}" alt="" width="96" height="96" loading="lazy" @class(['h-full w-full object-cover', 'opacity-50 grayscale' => $out])>
                                @endif
                            </a>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <a href="{{ route('product.show', $line['product']->slug) }}" class="line-clamp-2 font-medium leading-snug text-gray-900 hover:text-brand-600">{{ $line['product']->name }}</a>
                                        <p class="mt-0.5 text-sm text-gray-500">
                                            @if($line['variant'])সাইজ: <span class="font-medium text-gray-700">{{ $line['variant']->label }}</span> · @endif
                                            @taka($line['price'])
                                        </p>
                                    </div>
                                    <p class="shrink-0 font-bold tabular-nums text-gray-900">@taka($line['line_total'])</p>
                                </div>

                                @if($out)
                                    <p class="mt-2 text-sm font-medium text-error-500">স্টক শেষ</p>
                                @elseif($atMax && $line['stock'] <= \App\Models\Product::lowStockThreshold())
                                    <p class="mt-1 text-xs font-medium text-gray-600">মাত্র {{ $line['stock'] }}টি আছে</p>
                                @endif

                                <div class="mt-2 flex items-center justify-between gap-2">
                                    @unless($out)
                                        <div class="inline-flex items-center overflow-hidden rounded-xl border border-gray-300" role="group" aria-label="পরিমাণ">
                                            <button type="button" class="{{ $step }}" aria-label="{{ $line['quantity'] <= 1 ? 'কার্ট থেকে সরান' : 'পরিমাণ কমান' }}"
                                                wire:click="updateQuantity({{ $pid }}, {{ $vid }}, {{ $line['quantity'] - 1 }})" wire:loading.attr="disabled">−</button>
                                            <span class="w-9 text-center font-semibold tabular-nums" aria-live="polite">{{ $line['quantity'] }}</span>
                                            <button type="button" class="{{ $step }}" aria-label="পরিমাণ বাড়ান" @disabled($atMax) @if($atMax) title="স্টকে আর নেই" @endif
                                                wire:click="updateQuantity({{ $pid }}, {{ $vid }}, {{ $line['quantity'] + 1 }})" wire:loading.attr="disabled">+</button>
                                        </div>
                                    @else
                                        <span></span>
                                    @endunless
                                    <button type="button" wire:click="remove({{ $pid }}, {{ $vid }})" wire:loading.attr="disabled"
                                        class="inline-flex h-11 items-center gap-1.5 rounded-xl px-3 text-sm font-medium text-gray-500 hover:bg-error-50 hover:text-error-500" aria-label="{{ $line['product']->name }} কার্ট থেকে সরান">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>
                                        সরান
                                    </button>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('shop') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand-600">← আরও কেনাকাটা করুন</a>
            </div>

            <aside class="h-fit rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 lg:sticky lg:top-36">
                <h2 class="mb-4 font-semibold text-gray-900">অর্ডার সারাংশ</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-600">সাবটোটাল</dt><dd class="font-semibold tabular-nums">@taka($subtotal)</dd></div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-600">ডেলিভারি চার্জ</dt>
                        <dd class="text-right font-semibold">
                            @if(is_null($shippingFee))
                                <span class="block text-xs font-normal text-gray-500">ঢাকার ভেতরে @taka(\App\Support\Delivery::dhakaFee())</span>
                                <span class="block text-xs font-normal text-gray-500">ঢাকার বাইরে @taka(\App\Support\Delivery::outsideFee())</span>
                            @elseif($shippingFee == 0)
                                <span class="text-success-600">ফ্রি</span>
                            @else
                                @taka($shippingFee)
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between border-t border-gray-100 pt-3 text-lg font-bold">
                        <dt>মোট</dt><dd class="text-brand-600 tabular-nums">@taka($total)</dd>
                    </div>
                    @if(is_null($shippingFee))<p class="text-xs text-gray-500">ডেলিভারি চার্জ চেকআউটে এলাকা বাছাই করলে যোগ হবে।</p>@endif
                </dl>
                @if($freeRemaining)
                    <p class="mt-3 rounded-xl bg-success-50 px-3 py-2 text-xs font-medium text-success-700">আর @taka($freeRemaining) এর পণ্য কিনলে ডেলিভারি ফ্রি!</p>
                @endif
                <a href="{{ route('checkout') }}" class="mt-4 hidden h-12 items-center justify-center rounded-xl bg-brand-500 font-bold text-white hover:bg-brand-600 md:flex">চেকআউট করুন</a>
            </aside>
        </div>

        {{-- Phones: total + checkout always on screen --}}
        <div class="sticky-cta md:hidden fixed inset-x-3 bottom-above-mobile-nav z-30 flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-2 pl-4 shadow-[0_8px_24px_-8px_rgba(15,23,42,0.25)]">
            <div class="min-w-0 flex-1 leading-tight">
                <p class="text-xs text-gray-500">মোট @if(is_null($shippingFee))<span class="text-gray-400">(ডেলিভারি ছাড়া)</span>@endif</p>
                <p class="text-lg font-bold text-brand-600 tabular-nums">@taka($total)</p>
            </div>
            <a href="{{ route('checkout') }}" class="inline-flex h-12 shrink-0 items-center justify-center rounded-xl bg-brand-500 px-6 font-bold text-white">চেকআউট করুন</a>
        </div>
    @endif
</div>
