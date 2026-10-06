@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4">

    <section class="py-6 grid grid-cols-1 lg:grid-cols-[2fr_1fr] gap-4">
        <div class="relative rounded-xl overflow-hidden bg-gray-100 min-h-[220px] lg:min-h-[320px]">
            @if($heroBanners->isNotEmpty())
                <div x-data="{ i: 0, total: {{ $heroBanners->count() }} }" x-init="setInterval(() => i = (i + 1) % total, 4000)" class="relative h-full min-h-[220px] lg:min-h-[320px]">
                    @foreach($heroBanners as $idx => $banner)
                        <a href="{{ $banner->link ?: '#' }}" x-show="i === {{ $idx }}" x-transition.opacity class="absolute inset-0 block">
                            <img src="{{ $banner->image }}" alt="banner" class="h-full w-full object-cover">
                        </a>
                    @endforeach
                </div>
            @else
                <div class="h-full min-h-[220px] lg:min-h-[320px] flex items-center justify-center bg-gradient-to-br from-brand-100 to-brand-50 text-center p-8">
                    <div>
                        <h1 class="text-2xl font-bold text-ink mb-2">{{ \App\Support\SiteSettingsHelper::get('hero_title') ?: 'খাঁটি ও প্রাকৃতিক পণ্যের অনলাইন দোকান' }}</h1>
                        <p class="text-gray-500">{{ \App\Support\SiteSettingsHelper::get('hero_subtitle') ?: 'মধু, সরিষার তেল, ঘি, খেজুর — সরাসরি আপনার দোরগোড়ায়' }}</p>
                    </div>
                </div>
            @endif
        </div>
        <div class="grid grid-rows-2 gap-4">
            @foreach([0, 1] as $i)
                <a href="{{ $sideBanners[$i]->link ?? '#' }}" class="relative rounded-xl overflow-hidden bg-brand-50 min-h-[120px] flex items-center justify-center text-center p-4">
                    @if(isset($sideBanners[$i]))
                        <img src="{{ $sideBanners[$i]->image }}" alt="banner" class="absolute inset-0 h-full w-full object-cover">
                    @else
                        <div>
                            <p class="font-semibold text-gray-700">{{ \App\Support\SiteSettingsHelper::get('side_card_'.($i + 1).'_title') ?: ($i === 0 ? 'সেরা মানের মধু' : 'ফ্রি ডেলিভারি') }}</p>
                            <p class="text-xs text-gray-500">{{ \App\Support\SiteSettingsHelper::get('side_card_'.($i + 1).'_text') ?: ($i === 0 ? 'সরাসরি চাষী থেকে, ১০০% খাঁটি' : 'নির্দিষ্ট পরিমাণ কেনাকাটায়') }}</p>
                        </div>
                    @endif
                </a>
            @endforeach
        </div>
    </section>

    <section class="py-8">
        <h2 class="text-xl font-bold mb-4">ক্যাটাগরি</h2>
        <div class="flex gap-4 overflow-x-auto pb-2">
            @foreach($categories as $c)
                <a href="{{ route('shop', ['category' => $c->slug]) }}" class="shrink-0 w-28 sm:w-32 bg-white border border-gray-200 rounded-xl p-4 flex flex-col items-center gap-2 text-center font-medium hover:border-brand-500 hover:text-brand-500 transition-colors">
                    <span class="text-2xl">{{ $c->icon ?: '🛍️' }}</span>
                    <span class="text-sm truncate w-full">{{ $c->name }}</span>
                </a>
            @endforeach
        </div>
    </section>

    @if($activeFlashSale && $activeFlashSale->items->isNotEmpty())
        <section class="py-8">
            <div class="bg-secondary text-white rounded-xl p-5 mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold">⚡ {{ $activeFlashSale->title }}</h2>
                    <p class="text-white/70 text-sm">সীমিত সময়ের জন্য — শেষ হবে {{ $activeFlashSale->end_time->format('d M, h:i A') }}</p>
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                @foreach($activeFlashSale->items as $item)
                    @if($item->product)
                        <x-product-card :product="$item->product" />
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    @if($discounted->isNotEmpty())
        <section class="py-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold"><span class="text-brand-500">Hot Deal</span> — বিশেষ ছাড়</h2>
                <a href="{{ route('shop') }}" class="text-brand-500 text-sm font-medium">সব দেখুন →</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                @foreach($discounted as $p)
                    <x-product-card :product="$p" />
                @endforeach
            </div>
        </section>
    @endif

    @foreach($sectionProducts as $slug => $items)
        @continue($items->isEmpty())
        @php $cat = $sectionCategories[$slug]; @endphp
        <section class="py-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold">{{ $cat->name }}</h2>
                <a href="{{ route('shop', ['category' => $cat->slug]) }}" class="text-brand-500 text-sm font-medium">সব দেখুন →</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                @foreach($items as $p)
                    <x-product-card :product="$p" />
                @endforeach
            </div>
        </section>
    @endforeach

    <section class="py-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold">সব পণ্য</h2>
            <a href="{{ route('shop') }}" class="text-brand-500 text-sm font-medium">সব দেখুন →</a>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            @foreach($products as $p)
                <x-product-card :product="$p" />
            @endforeach
        </div>
    </section>

    @if($brands->isNotEmpty())
        <section class="py-8">
            <h2 class="text-xl font-bold mb-4">ব্র্যান্ডসমূহ</h2>
            <div class="flex flex-wrap items-center gap-6 bg-white border border-gray-200 rounded-xl p-6">
                @foreach($brands as $b)
                    @if($b->logo)
                        <img src="{{ $b->logo }}" alt="{{ $b->name }}" class="h-10 object-contain grayscale hover:grayscale-0 transition">
                    @else
                        <span class="text-gray-500 font-semibold text-sm px-3 py-1 border border-gray-200 rounded-full">{{ $b->name }}</span>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    @if($blogs->isNotEmpty())
        <section class="py-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold">ব্লগ</h2>
                <a href="{{ route('blog.index') }}" class="text-brand-500 text-sm font-medium">সব দেখুন →</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
                @foreach($blogs as $b)
                    <a href="{{ route('blog.show', $b->slug) }}" class="bg-white border border-gray-200 rounded-xl overflow-hidden hover:shadow-md transition-shadow">
                        <div class="aspect-video bg-gray-100">
                            @if($b->cover)
                                <img src="{{ $b->cover }}" alt="{{ $b->title }}" class="w-full h-full object-cover">
                            @endif
                        </div>
                        <div class="p-3">
                            <h3 class="text-sm font-semibold line-clamp-2">{{ $b->title }}</h3>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($promoBanners->isNotEmpty())
        <section class="py-8 grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($promoBanners->take(2) as $banner)
                <a href="{{ $banner->link ?: '#' }}" class="block h-40 md:h-48 rounded-xl overflow-hidden bg-gray-100">
                    <img src="{{ $banner->image }}" alt="promo" class="h-full w-full object-cover">
                </a>
            @endforeach
        </section>
    @endif

</div>
@endsection
