<div>
@if($product)
    @php
        $cartIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.25"/><circle cx="18" cy="20" r="1.25"/><path d="M2 3h3l2.6 12.1a1.5 1.5 0 0 0 1.5 1.2h8.7a1.5 1.5 0 0 0 1.5-1.2L21 7H6.2"/></svg>';
        $spinner = '<svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>';
    @endphp

    @if($mode === 'card')
        {{-- Card: one tap to order. Products with sizes go to their page to pick one. --}}
        @if($soldOut)
            <button type="button" disabled class="h-10 w-full rounded-xl bg-gray-100 text-sm font-semibold text-gray-400 cursor-not-allowed">স্টক নেই</button>
        @elseif($needsChoice)
            <div class="flex items-center gap-1.5 sm:gap-2">
                <a href="{{ route('product.show', $product->slug) }}" class="inline-flex h-10 min-w-0 flex-1 items-center justify-center whitespace-nowrap rounded-xl bg-brand-500 px-1 text-[13px] font-semibold text-white hover:bg-brand-600 sm:px-2 sm:text-sm">অর্ডার করুন</a>
                <a href="{{ route('product.show', $product->slug) }}" aria-label="সাইজ বেছে কার্টে যোগ করুন" title="সাইজ বেছে কার্টে যোগ করুন"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-300 text-gray-600 hover:border-brand-500 hover:text-brand-500">{!! $cartIcon !!}</a>
            </div>
        @else
            <div class="flex items-center gap-1.5 sm:gap-2">
                <button type="button" wire:click="buyNow" wire:loading.attr="disabled"
                    class="inline-flex h-10 min-w-0 flex-1 items-center justify-center gap-1 whitespace-nowrap rounded-xl bg-brand-500 px-1 text-[13px] font-semibold text-white hover:bg-brand-600 disabled:opacity-70 sm:px-2 sm:text-sm">
                    <span wire:loading wire:target="buyNow">{!! $spinner !!}</span>
                    অর্ডার করুন
                </button>
                <button type="button" wire:click="add" wire:loading.attr="disabled" aria-label="কার্টে যোগ করুন" title="কার্টে যোগ করুন"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-300 text-gray-600 hover:border-brand-500 hover:text-brand-500 disabled:opacity-60">
                    <span wire:loading.remove wire:target="add">{!! $cartIcon !!}</span>
                    <span wire:loading wire:target="add">{!! $spinner !!}</span>
                </button>
            </div>
        @endif
    @else
        @php
            $variants = $product->variants;
            $isSize = $variants->isNotEmpty() && $variants->every(fn ($v) => preg_match('/^(X{0,3}S|M|X{0,4}L|\d{1,3})$/i', trim($v->label)));
            $choiceLabel = $isSize ? 'সাইজ' : 'ধরন';
            $pricesDiffer = $variants->pluck('price')->map(fn ($p) => (float) $p)->unique()->count() > 1;
        @endphp
        <div class="flex flex-col gap-5">
            @if($variants->isNotEmpty())
                <fieldset>
                    <legend class="mb-2.5 text-sm font-medium text-gray-700">
                        {{ $choiceLabel }} বাছাই করুন@if($variant && ! $soldOut): <span class="font-bold text-gray-900">{{ $variant->label }}</span>@endif
                    </legend>
                    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="{{ $choiceLabel }}">
                        @foreach($variants as $v)
                            @php $none = $v->stock <= 0; $on = ! $none && $variant && $variant->id === $v->id; @endphp
                            <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}"
                                wire:click="selectVariant({{ $v->id }})" @disabled($none)
                                @if($none) aria-label="{{ $v->label }} — স্টক নেই" title="স্টক নেই" @endif
                                @class([
                                    'relative inline-flex min-h-11 min-w-12 flex-col items-center justify-center rounded-xl border-2 px-3 text-sm font-semibold transition',
                                    'border-brand-500 bg-brand-50 text-brand-600' => $on,
                                    'border-gray-200 bg-white text-gray-800 hover:border-gray-400' => ! $on && ! $none,
                                    'border-dashed border-gray-200 bg-gray-50 text-gray-300 line-through cursor-not-allowed' => $none,
                                ])>
                                <span>{{ $v->label }}</span>
                                @if($pricesDiffer)
                                    <span class="text-[11px] font-normal no-underline">@taka($v->price)</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            {{-- Stock state for the current choice --}}
            <p class="flex items-center gap-2 text-sm font-medium" aria-live="polite">
                @if($soldOut)
                    <span class="h-2 w-2 rounded-full bg-error-500"></span><span class="text-error-500">স্টক শেষ — শিগগিরই আবার আসবে</span>
                @elseif($outOfStock)
                    <span class="h-2 w-2 rounded-full bg-error-500"></span><span class="text-error-500">এই {{ $choiceLabel }} স্টকে নেই — অন্যটি বাছাই করুন</span>
                @elseif($lowStock)
                    <span class="h-2 w-2 rounded-full bg-accent"></span><span class="text-gray-800">মাত্র {{ $stock }}টি বাকি — তাড়াতাড়ি অর্ডার করুন</span>
                @else
                    <span class="h-2 w-2 rounded-full bg-success-600"></span><span class="text-success-700">স্টকে আছে</span>
                @endif
            </p>

            @unless($outOfStock)
                <div>
                    <span class="mb-2.5 block text-sm font-medium text-gray-700" id="qty-label-{{ $productId }}">পরিমাণ</span>
                    <div class="inline-flex h-11 items-center rounded-xl border border-gray-300 bg-white" role="group" aria-labelledby="qty-label-{{ $productId }}">
                        <button type="button" wire:click="decrement" @disabled($quantity <= 1) aria-label="পরিমাণ কমান" class="inline-flex h-full w-11 items-center justify-center rounded-l-xl text-lg text-gray-600 hover:bg-gray-50 disabled:text-gray-300">−</button>
                        <span class="w-10 text-center text-base font-semibold tabular-nums" aria-live="polite">{{ $quantity }}</span>
                        <button type="button" wire:click="increment" @disabled($quantity >= $stock) aria-label="পরিমাণ বাড়ান" class="inline-flex h-full w-11 items-center justify-center rounded-r-xl text-lg text-gray-600 hover:bg-gray-50 disabled:text-gray-300">+</button>
                    </div>
                </div>
            @endunless

            <div class="flex gap-3">
                <button type="button" wire:click="add" wire:loading.attr="disabled" @disabled($outOfStock)
                    class="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-xl border-2 border-brand-500 font-semibold text-brand-600 hover:bg-brand-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400 disabled:hover:bg-transparent">
                    <span wire:loading.remove wire:target="add">{!! $cartIcon !!}</span>
                    <span wire:loading wire:target="add">{!! $spinner !!}</span>
                    কার্টে যোগ করুন
                </button>
                <button type="button" wire:click="buyNow" wire:loading.attr="disabled" @disabled($outOfStock)
                    class="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-xl bg-brand-500 font-semibold text-white shadow-sm hover:bg-brand-600 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400 disabled:shadow-none">
                    <span wire:loading wire:target="buyNow">{!! $spinner !!}</span>
                    {{ $outOfStock ? 'স্টক নেই' : 'এখনই অর্ডার করুন' }}
                </button>
            </div>

            @if($message)
                <p class="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-xl bg-success-50 px-4 py-3 text-sm font-medium text-success-700" role="status">
                    ✓ {{ $message }}
                    <a href="{{ route('cart.index') }}" class="font-semibold underline">কার্ট দেখুন</a>
                </p>
            @endif
        </div>

        {{-- Phones: the buy buttons stay on screen, just above the bottom nav. --}}
        <div class="sticky-cta md:hidden fixed inset-x-3 bottom-above-mobile-nav z-30 flex items-center gap-2 rounded-2xl border border-gray-200 bg-white p-2 pl-4 shadow-[0_8px_24px_-8px_rgba(15,23,42,0.25)]">
            <div class="min-w-0 flex-1 leading-tight">
                <p class="text-lg font-bold text-brand-600 tabular-nums">@taka($unitPrice)</p>
                <p class="truncate text-xs text-gray-500">
                    @if($outOfStock) স্টক নেই @elseif($variant) {{ $choiceLabel }}: {{ $variant->label }} · {{ $quantity }}টি @else {{ $quantity }}টি @endif
                </p>
            </div>
            <button type="button" wire:click="add" wire:loading.attr="disabled" @disabled($outOfStock) aria-label="কার্টে যোগ করুন"
                class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border-2 border-brand-500 text-brand-600 disabled:border-gray-200 disabled:text-gray-300">
                <span wire:loading.remove wire:target="add">{!! $cartIcon !!}</span>
                <span wire:loading wire:target="add">{!! $spinner !!}</span>
            </button>
            <button type="button" wire:click="buyNow" wire:loading.attr="disabled" @disabled($outOfStock)
                class="inline-flex h-12 shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-500 px-5 font-semibold text-white disabled:bg-gray-200 disabled:text-gray-400">
                <span wire:loading wire:target="buyNow">{!! $spinner !!}</span>
                {{ $outOfStock ? 'স্টক নেই' : 'অর্ডার করুন' }}
            </button>
        </div>
    @endif
@endif
</div>
