@extends('layouts.app')

@section('title', ($product->seo_title ?: $product->name).' — '.\App\Support\SiteSettingsHelper::siteName())
@section('description', $product->meta_description ?: \App\Support\Seo::description($product->description))
@if($product->image)
    @section('og_image', $product->image)
@endif
@section('og_type', 'product')

@section('content')
@php
    $onSale = $product->hasDiscount();
    $price = $product->displayPrice();
    $gallery = collect([$product->image])
        ->concat($product->images->sortBy('sort_order')->pluck('url'))
        ->filter()->unique()->values();

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->name,
        'description' => \App\Support\Seo::description($product->description, 500) ?: $product->name,
        'sku' => $product->sku ?: (string) $product->id,
        'url' => route('product.show', $product->slug),
        'offers' => [
            '@type' => 'Offer',
            'url' => route('product.show', $product->slug),
            'priceCurrency' => 'BDT',
            'price' => number_format($price, 2, '.', ''),
            'availability' => $product->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
        ],
    ];
    if ($gallery->isNotEmpty()) {
        $schema['image'] = $gallery->map(fn ($u) => \App\Support\Media::absolute($u))->all();
    }
    if ($product->brand_id && $product->relationLoaded('brand') && $product->brand) {
        $schema['brand'] = ['@type' => 'Brand', 'name' => $product->brand->name];
    }
    if ($ratingCount > 0) {
        $schema['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => $ratingAvg,
            'reviewCount' => $ratingCount,
            'bestRating' => 5,
            'worstRating' => 1,
        ];
    }
@endphp
@push('head')
    <script type="application/ld+json">{!! \App\Support\Seo::jsonLd($schema) !!}</script>
@endpush
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.trackEvent) {
            window.trackEvent('view_item', @json(\App\Support\Tracking::product($product, (float) $price)));
        }
    });
</script>
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="grid md:grid-cols-2 gap-8">
        <div x-data="{ current: 0 }">
            <div class="relative aspect-square bg-surface rounded-xl overflow-hidden">
                @forelse($gallery as $i => $url)
                    <img src="{{ $url }}" alt="{{ $product->name }}" width="800" height="800"
                         @if($i === 0) fetchpriority="high" @else loading="lazy" @endif
                         x-show="current === {{ $i }}" @if($i > 0) x-cloak @endif
                         class="absolute inset-0 h-full w-full object-cover">
                @empty
                    <div class="absolute inset-0 flex items-center justify-center text-gray-300 text-5xl" aria-hidden="true">🛍</div>
                @endforelse
            </div>
            @if($gallery->count() > 1)
                <div class="mt-3 grid grid-cols-5 gap-2">
                    @foreach($gallery as $i => $url)
                        <button type="button" @click="current = {{ $i }}" aria-label="ছবি {{ $i + 1 }}"
                                :class="current === {{ $i }} ? 'ring-2 ring-brand-500' : 'ring-1 ring-gray-200'"
                                class="aspect-square overflow-hidden rounded-lg bg-surface">
                            <img src="{{ $url }}" alt="" width="120" height="120" loading="lazy" class="h-full w-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
        <div>
            <h1 class="text-2xl font-bold mb-2">{{ $product->name }}</h1>
            @if($ratingCount > 0)
                <a href="#reviews" class="mb-2 flex items-center gap-2 text-sm">
                    <span class="text-accent" aria-hidden="true">{{ str_repeat('★', (int) round($ratingAvg)) }}{{ str_repeat('☆', 5 - (int) round($ratingAvg)) }}</span>
                    <span class="text-gray-600">{{ $ratingAvg }} ({{ $ratingCount }} রিভিউ)</span>
                </a>
            @endif
            <div class="flex items-baseline gap-3 mb-4">
                <span class="text-2xl font-bold text-brand-500">@taka($price)</span>
                @if($onSale)
                    <span class="text-gray-400 line-through">@taka($product->price)</span>
                @endif
            </div>
            <p class="text-sm text-gray-500 mb-4">
                স্টক: {{ $product->stock > 0 ? "{$product->stock} টি আছে" : 'স্টক নেই' }}
            </p>
            <div class="max-w-xs">
                @livewire('product.add-to-cart', ['productId' => $product->id, 'mode' => 'detail', 'product' => $product])
            </div>
        </div>
    </div>

    @if(filled($product->description))
        <section class="mt-12 rounded-xl border border-gray-200 bg-white p-6" aria-labelledby="product-details">
            <h2 id="product-details" class="mb-4 text-xl font-bold">পণ্যের বিস্তারিত বিবরণ</h2>
            <div class="rich-text text-gray-700 leading-relaxed">{!! \App\Support\Html::render($product->description) !!}</div>
        </section>
    @endif

    <div id="reviews" class="mt-12 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold">রিভিউ ও রেটিং ({{ $product->reviews->count() }})</h2>
            <button type="button" onclick="document.getElementById('review-form').classList.toggle('hidden')" class="bg-brand-500 text-white px-4 py-2 rounded font-medium hover:bg-brand-600">রিভিউ লিখুন</button>
        </div>

        @if(session('status'))
            <div class="mb-6 p-4 bg-success-50 text-success-700 border border-success-200 rounded">{{ session('status') }}</div>
        @endif

        <form id="review-form" action="{{ route('product.reviews.store', $product->slug) }}" method="POST" class="hidden mb-8 p-4 bg-surface border border-gray-200 rounded-lg">
            @csrf
            <h3 class="font-semibold mb-4">আপনার রিভিউ লিখুন</h3>
            <div class="grid md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">আপনার নাম <span class="text-error-500">*</span></label>
                    <input type="text" name="customer_name" required class="w-full border border-gray-300 p-2 rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">রেটিং <span class="text-error-500">*</span></label>
                    <select name="rating" class="w-full border border-gray-300 p-2 rounded">
                        @for($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}">{{ $i }} স্টার</option>
                        @endfor
                    </select>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">মন্তব্য</label>
                <textarea name="comment" rows="3" class="w-full border border-gray-300 p-2 rounded"></textarea>
            </div>
            <button type="submit" class="bg-brand-500 text-white px-4 py-2 rounded font-medium hover:bg-brand-600">সাবমিট করুন</button>
        </form>

        <div class="space-y-4">
            @forelse($product->reviews as $review)
                <div class="border-b border-gray-100 pb-4">
                    <div class="flex items-center justify-between">
                        <span class="font-medium">{{ $review->customer_name }}</span>
                        <span class="text-brand-500 text-sm">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                    </div>
                    @if($review->comment)
                        <p class="text-gray-600 text-sm mt-1">{{ $review->comment }}</p>
                    @endif
                </div>
            @empty
                <p class="text-gray-400 text-sm">এখনো কোনো রিভিউ নেই।</p>
            @endforelse
        </div>
    </div>

    @if($similarProducts->isNotEmpty())
        <div class="mt-16">
            <h2 class="text-2xl font-bold mb-6">সম্পর্কিত প্রোডাক্ট</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                @foreach($similarProducts as $p)
                    <x-product-card :product="$p" />
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
