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
            'availability' => ! $product->isSoldOut() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
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
<div class="max-w-7xl mx-auto px-4 py-4 md:py-8">
    <nav aria-label="ব্রেডক্রাম্ব" class="mb-4 overflow-hidden text-sm text-gray-500">
        <ol class="flex items-center gap-1.5 whitespace-nowrap">
            <li><a href="{{ url('/') }}" class="hover:text-brand-600">হোম</a></li>
            @if($product->category)
                <li aria-hidden="true">›</li>
                <li><a href="{{ route('shop', ['category' => $product->category->slug]) }}" class="hover:text-brand-600">{{ $product->category->name }}</a></li>
            @endif
            <li aria-hidden="true">›</li>
            <li class="min-w-0 truncate text-gray-700" aria-current="page">{{ $product->name }}</li>
        </ol>
    </nav>

    <div class="grid gap-6 md:grid-cols-2 md:gap-10">
        {{-- Gallery: swipe on phones (CSS scroll-snap), arrows + thumbnails on bigger screens. --}}
        <div x-data="{
                current: 0,
                count: {{ $gallery->count() }},
                go(i) { i = (i + this.count) % this.count; this.$refs.track.scrollTo({ left: i * this.$refs.track.clientWidth, behavior: 'smooth' }); },
                sync() { this.current = Math.round(this.$refs.track.scrollLeft / Math.max(1, this.$refs.track.clientWidth)); },
            }" class="min-w-0 md:sticky md:top-32 md:self-start">
            <div class="relative overflow-hidden rounded-2xl bg-surface">
                <div x-ref="track" @scroll.debounce.60ms="sync()" class="flex snap-x snap-mandatory overflow-x-auto scrollbar-none" tabindex="0" aria-label="পণ্যের ছবি — পাশে সরিয়ে দেখুন">
                    @forelse($gallery as $i => $url)
                        <div class="relative aspect-square w-full shrink-0 snap-center snap-always">
                            <img src="{{ $url }}" alt="{{ $product->name }}{{ $gallery->count() > 1 ? ' — ছবি '.($i + 1) : '' }}" width="800" height="800"
                                 @if($i === 0) fetchpriority="high" @else loading="lazy" @endif
                                 class="absolute inset-0 h-full w-full object-cover" draggable="false">
                        </div>
                    @empty
                        <div class="flex aspect-square w-full items-center justify-center text-5xl text-gray-300" aria-hidden="true">🛍</div>
                    @endforelse
                </div>
                @if($onSale && $product->discountPercent() > 0)
                    <span class="pointer-events-none absolute left-3 top-3 rounded-lg bg-brand-500 px-2.5 py-1 text-sm font-bold text-white">-{{ $product->discountPercent() }}%</span>
                @endif
                @if($gallery->count() > 1)
                    <span class="pointer-events-none absolute right-3 top-3 rounded-full bg-black/55 px-2.5 py-1 text-xs font-medium text-white tabular-nums" x-text="(current + 1) + '/' + count">1/{{ $gallery->count() }}</span>
                    <button type="button" @click="go(current - 1)" aria-label="আগের ছবি" class="absolute left-3 top-1/2 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow hover:bg-white md:flex">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button type="button" @click="go(current + 1)" aria-label="পরের ছবি" class="absolute right-3 top-1/2 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow hover:bg-white md:flex">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                    <div class="pointer-events-none absolute inset-x-0 bottom-3 flex justify-center gap-1.5 md:hidden" aria-hidden="true">
                        @foreach($gallery as $i => $url)
                            <span class="h-1.5 rounded-full bg-white shadow transition-all" :class="current === {{ $i }} ? 'w-5 opacity-100' : 'w-1.5 opacity-60'"></span>
                        @endforeach
                    </div>
                @endif
            </div>
            @if($gallery->count() > 1)
                <div class="mt-3 hidden gap-2 overflow-x-auto scrollbar-none md:flex">
                    @foreach($gallery as $i => $url)
                        <button type="button" @click="go({{ $i }})" aria-label="ছবি {{ $i + 1 }}"
                                :class="current === {{ $i }} ? 'ring-2 ring-brand-500' : 'ring-1 ring-gray-200 opacity-80 hover:opacity-100'"
                                class="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-surface">
                            <img src="{{ $url }}" alt="" width="120" height="120" loading="lazy" class="h-full w-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="min-w-0">
            @if($product->brand)
                <a href="{{ route('shop', ['brand' => $product->brand->id]) }}" class="mb-1 inline-block text-sm font-medium text-brand-600 hover:underline">{{ $product->brand->name }}</a>
            @endif
            <h1 class="text-xl font-bold leading-snug text-gray-900 sm:text-2xl">{{ $product->name }}</h1>
            @if($ratingCount > 0)
                <a href="#reviews" class="mt-2 inline-flex items-center gap-2 text-sm">
                    <span class="text-accent" aria-hidden="true">{{ str_repeat('★', (int) round($ratingAvg)) }}{{ str_repeat('☆', 5 - (int) round($ratingAvg)) }}</span>
                    <span class="text-gray-600">{{ $ratingAvg }} ({{ $ratingCount }} রিভিউ)</span>
                </a>
            @endif
            <div class="mt-3 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="text-3xl font-bold text-brand-600 tabular-nums">@taka($price)</span>
                @if($onSale)
                    <span class="text-lg text-gray-400 line-through tabular-nums">@taka($product->price)</span>
                    <span class="rounded-md bg-brand-50 px-2 py-0.5 text-sm font-semibold text-brand-600">@taka($product->price - $price) সাশ্রয়</span>
                @endif
            </div>
            <div class="mt-6">
                @livewire('product.add-to-cart', ['productId' => $product->id, 'mode' => 'detail', 'product' => $product])
            </div>
            @if($product->sku)
                <p class="mt-6 text-xs text-gray-400">SKU: {{ $product->sku }}</p>
            @endif
        </div>
    </div>

    @if(filled($product->description))
        <section class="mt-10 rounded-2xl border border-gray-200 bg-white p-5 sm:p-6" aria-labelledby="product-details">
            <h2 id="product-details" class="mb-4 text-lg font-bold sm:text-xl">পণ্যের বিস্তারিত বিবরণ</h2>
            <div class="rich-text break-words leading-relaxed text-gray-700">{!! \App\Support\Html::render($product->description) !!}</div>
        </section>
    @endif

    <section id="reviews" class="mt-6 scroll-mt-32 rounded-2xl border border-gray-200 bg-white p-5 sm:p-6" aria-labelledby="reviews-title"
        x-data="{ open: {{ $errors->hasAny(['customer_name', 'rating', 'comment']) ? 'true' : 'false' }}, rating: {{ (int) old('rating', 5) }} }">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <h2 id="reviews-title" class="text-lg font-bold sm:text-xl">রিভিউ ও রেটিং ({{ $product->reviews->count() }})</h2>
            <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-controls="review-form"
                class="inline-flex h-10 items-center rounded-xl border-2 border-brand-500 px-4 text-sm font-semibold text-brand-600 hover:bg-brand-50">রিভিউ লিখুন</button>
        </div>

        @if(session('status'))
            <div class="mb-5 rounded-xl border border-success-200 bg-success-50 p-4 text-sm text-success-700" role="status">{{ session('status') }}</div>
        @endif

        <form id="review-form" x-show="open" x-cloak action="{{ route('product.reviews.store', $product->slug) }}" method="POST" class="mb-6 space-y-4 rounded-2xl bg-surface p-4 sm:p-5">
            @csrf
            <h3 class="font-semibold">আপনার রিভিউ লিখুন</h3>
            <div>
                <span class="mb-2 block text-sm font-medium text-gray-700">রেটিং <span class="text-error-500">*</span></span>
                <div class="flex gap-1" role="radiogroup" aria-label="রেটিং">
                    @for($i = 1; $i <= 5; $i++)
                        <label class="cursor-pointer">
                            <input type="radio" name="rating" value="{{ $i }}" x-model.number="rating" class="peer sr-only" @checked((int) old('rating', 5) === $i)>
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-3xl leading-none peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500"
                                :class="rating >= {{ $i }} ? 'text-accent' : 'text-gray-300'" aria-label="{{ $i }} স্টার">★</span>
                        </label>
                    @endfor
                </div>
            </div>
            <x-form-input name="customer_name" label="আপনার নাম" icon="user" maxlength="255" autocomplete="name" required />
            <div class="flex flex-col gap-1.5">
                <label for="review-comment" class="text-sm font-medium text-gray-700">মন্তব্য</label>
                <textarea id="review-comment" name="comment" rows="3" maxlength="2000" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-base focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">{{ old('comment') }}</textarea>
            </div>
            <button type="submit" class="inline-flex h-12 w-full items-center justify-center rounded-xl bg-brand-500 font-semibold text-white hover:bg-brand-600 sm:w-auto sm:px-8">রিভিউ জমা দিন</button>
            <p class="text-xs text-gray-500">যাচাইয়ের পর রিভিউটি প্রকাশ করা হবে।</p>
        </form>

        <div class="divide-y divide-gray-100">
            @forelse($product->reviews as $review)
                <article class="py-4 first:pt-0">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-medium">{{ $review->customer_name }}</span>
                        <span class="text-sm text-accent" aria-label="{{ $review->rating }} স্টার">{{ str_repeat('★', $review->rating) }}<span class="text-gray-300">{{ str_repeat('★', 5 - $review->rating) }}</span></span>
                    </div>
                    @if($review->comment)
                        <p class="mt-1 break-words text-sm text-gray-600">{{ $review->comment }}</p>
                    @endif
                </article>
            @empty
                <p class="text-sm text-gray-500">এখনো কোনো রিভিউ নেই। প্রথম রিভিউটি আপনিই লিখুন!</p>
            @endforelse
        </div>
    </section>

    @if($similarProducts->isNotEmpty())
        <section class="mt-10" aria-labelledby="related-title">
            <h2 id="related-title" class="mb-4 text-lg font-bold sm:text-xl">সম্পর্কিত পণ্য</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 md:grid-cols-4">
                @foreach($similarProducts as $p)
                    <x-product-card :product="$p" />
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
