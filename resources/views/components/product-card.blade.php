@props(['product'])
@php
    $onSale = $product->hasDiscount();
    $price = $product->displayPrice();
    $discountPct = $product->discountPercent();
    $outOfStock = $product->stock <= 0;
@endphp
<div class="group bg-white border border-gray-200 rounded-xl overflow-hidden flex flex-col hover:shadow-md transition-shadow">
    <a href="{{ route('product.show', $product->slug) }}" class="block relative aspect-square bg-surface">
        @if($product->image)
            <img src="{{ $product->image }}" alt="{{ $product->name }}" width="400" height="400" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover">
        @endif
        @if($onSale && $discountPct > 0)
            <span class="absolute top-2 left-2 bg-brand-500 text-white text-xs font-bold px-2 py-0.5 rounded">-{{ $discountPct }}%</span>
        @endif
        @if($outOfStock)
            <span class="absolute inset-0 bg-white/70 flex items-center justify-center text-sm font-semibold text-gray-600">স্টক নেই</span>
        @endif
    </a>
    <div class="p-3 flex flex-col gap-2 flex-1">
        <a href="{{ route('product.show', $product->slug) }}" class="text-sm font-medium line-clamp-2 hover:text-brand-500">{{ $product->name }}</a>
        @if(! empty($product->approved_reviews_count))
            <div class="flex items-center gap-1 text-xs" aria-label="রেটিং {{ number_format($product->approved_reviews_avg, 1) }} / ৫">
                <span class="text-accent" aria-hidden="true">{{ str_repeat('★', (int) round($product->approved_reviews_avg)) }}{{ str_repeat('☆', 5 - (int) round($product->approved_reviews_avg)) }}</span>
                <span class="text-gray-500">({{ $product->approved_reviews_count }})</span>
            </div>
        @endif
        <div class="flex items-baseline gap-2">
            <span class="text-brand-500 font-bold">@taka($price)</span>
            @if($onSale)
                <span class="text-gray-400 text-xs line-through">@taka($product->price)</span>
            @endif
        </div>
        <div class="mt-auto">
            @livewire('product.add-to-cart', ['productId' => $product->id, 'mode' => 'card', 'product' => $product], key('add-to-cart-card-'.$product->id))
        </div>
    </div>
</div>
