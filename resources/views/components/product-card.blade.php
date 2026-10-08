@props(['product'])
@php
    $onSale = $product->hasDiscount();
    $price = $product->displayPrice();
    $discountPct = $product->discountPercent();
    $soldOut = $product->isSoldOut();
    $url = route('product.show', $product->slug);
@endphp
{{-- Two to a row on phones: the name always takes two lines' height so prices and buttons line up. --}}
<article class="group flex min-w-0 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white transition hover:border-gray-300 hover:shadow-md">
    <a href="{{ $url }}" class="relative block aspect-square overflow-hidden bg-surface" tabindex="-1" aria-hidden="true">
        @if($product->image)
            <img src="{{ $product->image }}" alt="" width="400" height="400" loading="lazy" decoding="async"
                @class(['absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]', 'opacity-60 grayscale' => $soldOut])>
        @else
            <span class="absolute inset-0 flex items-center justify-center text-4xl text-gray-300">🛍</span>
        @endif
        @if($soldOut)
            <span class="absolute left-2 top-2 rounded-lg bg-gray-900/80 px-2 py-1 text-xs font-semibold text-white">স্টক নেই</span>
        @elseif($onSale && $discountPct > 0)
            <span class="absolute left-2 top-2 rounded-lg bg-brand-500 px-2 py-1 text-xs font-bold text-white tabular-nums">-{{ $discountPct }}%</span>
        @endif
    </a>
    <div class="flex flex-1 flex-col gap-1.5 p-2 sm:p-3">
        <h3 class="text-sm font-medium leading-snug text-gray-800">
            <a href="{{ $url }}" class="line-clamp-2 min-h-[2.75em] hover:text-brand-600">{{ $product->name }}</a>
        </h3>
        @if(! empty($product->approved_reviews_count))
            <div class="flex items-center gap-1 text-xs" aria-label="রেটিং {{ number_format($product->approved_reviews_avg, 1) }} / ৫">
                <span class="text-accent" aria-hidden="true">{{ str_repeat('★', (int) round($product->approved_reviews_avg)) }}{{ str_repeat('☆', 5 - (int) round($product->approved_reviews_avg)) }}</span>
                <span class="text-gray-500">({{ $product->approved_reviews_count }})</span>
            </div>
        @endif
        <div class="flex flex-wrap items-baseline gap-x-2">
            <span class="text-base font-bold text-brand-600 tabular-nums">@taka($price)</span>
            @if($onSale)
                <span class="text-xs text-gray-400 line-through tabular-nums">@taka($product->price)</span>
            @endif
        </div>
        <div class="mt-auto pt-1">
            @livewire('product.add-to-cart', ['productId' => $product->id, 'mode' => 'card', 'product' => $product], key('add-to-cart-card-'.$product->id))
        </div>
    </div>
</article>
