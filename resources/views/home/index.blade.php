@extends('layouts.app')

@section('content')
@php
    $settings = \App\Support\SiteSettingsHelper::class;
    $grid = 'grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 md:grid-cols-4';
@endphp
<div class="max-w-7xl mx-auto px-4">
    @if($heroBanners->isNotEmpty())
        <h1 class="sr-only">{{ $settings::get('hero_title') ?: $settings::siteName().' — '.$settings::siteNameEn() }}</h1>
    @endif

    <section class="grid grid-cols-1 gap-3 pt-4 pb-2 md:gap-4 md:py-6 lg:grid-cols-[2fr_1fr]">
        {{-- Hero: banners swipe on phones (scroll-snap) and auto-advance; text card when there are none. --}}
        <div class="relative overflow-hidden rounded-2xl bg-gray-100">
            @if($heroBanners->isNotEmpty())
                <div x-data="{
                        i: 0,
                        total: {{ $heroBanners->count() }},
                        timer: null,
                        go(n) { this.i = (n + this.total) % this.total; this.$refs.track.scrollTo({ left: this.i * this.$refs.track.clientWidth, behavior: 'smooth' }); },
                        sync() { this.i = Math.round(this.$refs.track.scrollLeft / Math.max(1, this.$refs.track.clientWidth)); },
                        play() { if (this.total > 1 && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches) this.timer = setInterval(() => this.go(this.i + 1), 5000); },
                        stop() { clearInterval(this.timer); },
                    }" x-init="play()" @touchstart.passive="stop()" @mouseenter="stop()" @mouseleave="stop(); play()">
                    <div x-ref="track" @scroll.debounce.80ms="sync()" class="flex snap-x snap-mandatory overflow-x-auto scrollbar-none" aria-roledescription="carousel" aria-label="ব্যানার">
                        @foreach($heroBanners as $idx => $banner)
                            <a href="{{ $banner->link ?: '#' }}" @unless($banner->link) tabindex="-1" @endunless
                                class="relative block aspect-[2/1] w-full shrink-0 snap-center snap-always lg:aspect-auto lg:h-[340px]"
                                aria-label="ব্যানার {{ $idx + 1 }} / {{ $heroBanners->count() }}">
                                <img src="{{ $banner->image }}" alt="{{ $banner->title ?? '' }}" width="1200" height="600" @if($idx === 0) fetchpriority="high" @else loading="lazy" @endif class="absolute inset-0 h-full w-full object-cover" draggable="false">
                            </a>
                        @endforeach
                    </div>
                    @if($heroBanners->count() > 1)
                        <div class="absolute inset-x-0 bottom-2.5 flex justify-center gap-1.5">
                            @foreach($heroBanners as $idx => $banner)
                                <button type="button" @click="stop(); go({{ $idx }})" aria-label="ব্যানার {{ $idx + 1 }}"
                                    class="flex h-6 items-center px-0.5"><span class="block h-1.5 rounded-full bg-white shadow transition-all" :class="i === {{ $idx }} ? 'w-5' : 'w-1.5 opacity-60'"></span></button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <div class="flex min-h-[200px] items-center justify-center bg-gradient-to-br from-brand-100 to-brand-50 p-6 text-center lg:min-h-[340px] lg:p-10">
                    <div>
                        <h1 class="text-xl font-bold text-ink sm:text-2xl lg:text-3xl">{{ $settings::get('hero_title') ?: 'খাঁটি ও প্রাকৃতিক পণ্যের অনলাইন দোকান' }}</h1>
                        <p class="mt-2 text-sm text-gray-600 sm:text-base">{{ $settings::get('hero_subtitle') ?: 'মধু, সরিষার তেল, ঘি, খেজুর — সরাসরি আপনার দোরগোড়ায়' }}</p>
                        <a href="{{ route('shop') }}" class="mt-5 inline-flex h-11 items-center rounded-xl bg-brand-500 px-6 text-sm font-semibold text-white hover:bg-brand-600">কেনাকাটা শুরু করুন</a>
                    </div>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-3 md:gap-4 lg:grid-cols-1 lg:grid-rows-2">
            @foreach([0, 1] as $i)
                @php
                    $side = $sideBanners[$i] ?? null;
                    $tag = $side && $side->link ? 'a' : 'div';
                @endphp
                <{{ $tag }} @if($tag === 'a') href="{{ $side->link }}" @endif class="relative flex min-h-[96px] items-center justify-center overflow-hidden rounded-2xl bg-brand-50 p-3 text-center sm:min-h-[120px]">
                    @if($side)
                        <img src="{{ $side->image }}" alt="{{ $side->title ?? '' }}" width="600" height="300" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
                    @else
                        <div>
                            <p class="text-sm font-semibold text-gray-800 sm:text-base">{{ $settings::get('side_card_'.($i + 1).'_title') ?: ($i === 0 ? 'সেরা মানের মধু' : 'ফ্রি ডেলিভারি') }}</p>
                            <p class="mt-0.5 text-xs text-gray-500">{{ $settings::get('side_card_'.($i + 1).'_text') ?: ($i === 0 ? 'সরাসরি চাষী থেকে, ১০০% খাঁটি' : 'নির্দিষ্ট পরিমাণ কেনাকাটায়') }}</p>
                        </div>
                    @endif
                </{{ $tag }}>
            @endforeach
        </div>
    </section>

    @if($categories->isNotEmpty())
        <section class="py-5 md:py-8" aria-labelledby="home-cats">
            <div class="mb-3 flex items-center justify-between md:mb-4">
                <h2 id="home-cats" class="text-lg font-bold md:text-xl">ক্যাটাগরি</h2>
                <a href="{{ route('shop') }}" class="-my-2 inline-flex min-h-10 items-center text-sm font-medium text-brand-600">সব দেখুন →</a>
            </div>
            <div class="-mx-4 flex snap-x gap-3 overflow-x-auto px-4 pb-1 scrollbar-none md:mx-0 md:grid md:grid-cols-6 md:overflow-visible md:px-0 lg:grid-cols-8">
                @foreach($categories as $c)
                    <a href="{{ route('shop', ['category' => $c->slug]) }}" class="flex w-24 shrink-0 snap-start flex-col items-center gap-2 rounded-2xl border border-gray-200 bg-white p-3 text-center transition-colors hover:border-brand-500 hover:text-brand-600 md:w-auto">
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-50">
                            @if(\App\Filament\Resources\Categories\CategoryResource::isImageIcon($c->icon))
                                <img src="{{ $c->icon }}" alt="" width="28" height="28" loading="lazy" class="h-7 w-7 object-contain">
                            @else
                                <span class="text-2xl leading-none">{{ $c->icon ?: '🛍️' }}</span>
                            @endif
                        </span>
                        <span class="line-clamp-2 text-xs font-medium leading-tight sm:text-sm">{{ $c->name }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($activeFlashSale && $activeFlashSale->items->isNotEmpty())
        <section class="py-5 md:py-8">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-secondary p-4 text-white md:p-5">
                <div class="min-w-0">
                    <h2 class="text-lg font-bold md:text-xl">⚡ {{ $activeFlashSale->title }}</h2>
                    <p class="text-sm text-white/70">সীমিত সময়ের অফার — শেষ হবে {{ $activeFlashSale->end_time->locale('bn')->translatedFormat('j F, g:i A') }}</p>
                </div>
            </div>
            <div class="{{ $grid }}">
                @foreach($activeFlashSale->items as $item)
                    @if($item->product)
                        <x-product-card :product="$item->product" />
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    @if($discounted->isNotEmpty())
        <section class="py-5 md:py-8">
            <div class="mb-3 flex items-center justify-between gap-3 md:mb-4">
                <h2 class="text-lg font-bold md:text-xl">বিশেষ <span class="text-brand-600">ছাড়</span></h2>
                <a href="{{ route('offers') }}" class="-my-2 inline-flex min-h-10 shrink-0 items-center text-sm font-medium text-brand-600">সব দেখুন →</a>
            </div>
            <div class="{{ $grid }}">
                @foreach($discounted as $p)
                    <x-product-card :product="$p" />
                @endforeach
            </div>
        </section>
    @endif

    @foreach($sectionProducts as $slug => $items)
        @continue($items->isEmpty())
        @php $cat = $sectionCategories[$slug]; @endphp
        <section class="py-5 md:py-8">
            <div class="mb-3 flex items-center justify-between gap-3 md:mb-4">
                <h2 class="min-w-0 truncate text-lg font-bold md:text-xl">{{ $cat->name }}</h2>
                <a href="{{ route('shop', ['category' => $cat->slug]) }}" class="-my-2 inline-flex min-h-10 shrink-0 items-center text-sm font-medium text-brand-600">সব দেখুন →</a>
            </div>
            <div class="{{ $grid }}">
                @foreach($items as $p)
                    <x-product-card :product="$p" />
                @endforeach
            </div>
        </section>
    @endforeach

    @if($products->isNotEmpty())
        <section class="py-5 md:py-8">
            <div class="mb-3 flex items-center justify-between gap-3 md:mb-4">
                <h2 class="text-lg font-bold md:text-xl">নতুন পণ্য</h2>
                <a href="{{ route('shop') }}" class="-my-2 inline-flex min-h-10 shrink-0 items-center text-sm font-medium text-brand-600">সব দেখুন →</a>
            </div>
            <div class="{{ $grid }}">
                @foreach($products as $p)
                    <x-product-card :product="$p" />
                @endforeach
            </div>
        </section>
    @else
        <section class="py-10">
            <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center">
                <p class="text-lg font-semibold text-gray-800">শিগগিরই নতুন পণ্য আসছে</p>
                <p class="mt-1 text-sm text-gray-500">একটু পরে আবার দেখুন।</p>
            </div>
        </section>
    @endif

    @if($brands->isNotEmpty())
        <section class="py-5 md:py-8">
            <div class="mb-3 flex items-center justify-between md:mb-4">
                <h2 class="text-lg font-bold md:text-xl">ব্র্যান্ডসমূহ</h2>
                <a href="{{ route('brands') }}" class="-my-2 inline-flex min-h-10 items-center text-sm font-medium text-brand-600">সব দেখুন →</a>
            </div>
            <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 md:gap-4 md:p-6">
                @foreach($brands as $b)
                    <a href="{{ route('shop', ['brand' => $b->id]) }}" class="inline-flex min-h-11 items-center rounded-xl px-2 hover:bg-gray-50" aria-label="{{ $b->name }}">
                        @if($b->logo)
                            <img src="{{ $b->logo }}" alt="{{ $b->name }}" width="120" height="40" loading="lazy" class="h-9 w-auto max-w-[120px] object-contain grayscale transition hover:grayscale-0">
                        @else
                            <span class="rounded-full border border-gray-200 px-3 py-1.5 text-sm font-semibold text-gray-600">{{ $b->name }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($blogs->isNotEmpty())
        <section class="py-5 md:py-8">
            <div class="mb-3 flex items-center justify-between md:mb-4">
                <h2 class="text-lg font-bold md:text-xl">ব্লগ</h2>
                <a href="{{ route('blog.index') }}" class="-my-2 inline-flex min-h-10 items-center text-sm font-medium text-brand-600">সব দেখুন →</a>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-5">
                @foreach($blogs as $b)
                    <a href="{{ route('blog.show', $b->slug) }}" class="overflow-hidden rounded-2xl border border-gray-200 bg-white transition-shadow hover:shadow-md">
                        <div class="aspect-video bg-gray-100">
                            @if($b->cover)
                                <img src="{{ $b->cover }}" alt="" width="400" height="225" loading="lazy" decoding="async" class="h-full w-full object-cover">
                            @endif
                        </div>
                        <div class="p-3">
                            <h3 class="line-clamp-2 text-sm font-semibold">{{ $b->title }}</h3>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($promoBanners->isNotEmpty())
        <section class="grid grid-cols-1 gap-3 py-5 md:grid-cols-2 md:gap-4 md:py-8">
            @foreach($promoBanners->take(2) as $banner)
                @php $tag = $banner->link ? 'a' : 'div'; @endphp
                <{{ $tag }} @if($banner->link) href="{{ $banner->link }}" @endif class="block aspect-[5/2] overflow-hidden rounded-2xl bg-gray-100 md:aspect-auto md:h-48">
                    <img src="{{ $banner->image }}" alt="{{ $banner->title ?? '' }}" width="800" height="320" loading="lazy" class="h-full w-full object-cover">
                </{{ $tag }}>
            @endforeach
        </section>
    @endif
</div>
@endsection
