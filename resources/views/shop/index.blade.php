@extends('layouts.app')

@php
    // Page heading: what is being shown (brand / category / search / plain listing).
    $heading = $activeBrand->name ?? $activeCategory->name ?? ($search !== '' ? '“'.$search.'” এর ফলাফল' : $title);
    $sorts = ['newest' => 'নতুন আগে', 'price_asc' => 'দাম: কম থেকে বেশি', 'price_desc' => 'দাম: বেশি থেকে কম'];
    $sort = array_key_exists($sortKey, $sorts) ? $sortKey : 'newest';
    // Links that change one filter and go back to page 1.
    $with = fn (array $q) => request()->fullUrlWithQuery($q + ['page' => null]);
    // The same page with one ticked value (or a whole filter) taken off.
    $without = function (string $key, ?string $value = null): string {
        $q = request()->query();
        unset($q['page']);

        if ($key === 'price') {
            unset($q['price_min'], $q['price_max']);
        } elseif ($value !== null && isset($q[$key])) {
            $rest = array_values(array_diff((array) $q[$key], [$value]));
            $rest ? $q[$key] = $rest : $q = array_diff_key($q, [$key => 1]);
        } else {
            unset($q[$key]);
        }

        return url()->current().($q ? '?'.http_build_query($q) : '');
    };
    $activeCount = $filterCount + ($search !== '' ? 1 : 0);
    // "Clear filters" keeps the search words.
    $clearFilters = url()->current().($search !== '' ? '?q='.urlencode($search) : '');
    $chip = 'inline-flex h-10 shrink-0 items-center rounded-full border px-4 text-sm font-medium transition-colors';
    $chipOn = 'border-brand-500 bg-brand-500 text-white';
    $chipOff = 'border-gray-200 bg-white text-gray-700 hover:border-gray-400';

    // Every ticked filter as a removable pill.
    $pills = [];
    foreach ($filters['category'] as $slug) {
        $pills[] = ['label' => $categories->firstWhere('slug', $slug)?->name ?? $slug, 'url' => $without('category', $slug)];
    }
    foreach ($filters['brand'] as $id) {
        $pills[] = ['label' => $brands->firstWhere('id', (int) $id)?->name ?? $id, 'url' => $without('brand', $id)];
    }
    foreach ($filters['color'] as $color) {
        $pills[] = ['label' => $color, 'url' => $without('color', $color)];
    }
    foreach ($filters['size'] as $size) {
        $pills[] = ['label' => 'সাইজ '.$size, 'url' => $without('size', $size)];
    }
    if ($filters['price_min'] !== null || $filters['price_max'] !== null) {
        $pills[] = ['label' => '৳'.($filters['price_min'] ?? 0).' – '.($filters['price_max'] !== null ? '৳'.$filters['price_max'] : 'যত বেশি'), 'url' => $without('price')];
    }
    if ($filters['in_stock']) {
        $pills[] = ['label' => 'শুধু স্টকে আছে', 'url' => $without('in_stock')];
    }
    if ($search !== '') {
        $pills[] = ['label' => '“'.$search.'”', 'url' => $without('q')];
    }
@endphp

@section('title', $heading.' — '.\App\Support\SiteSettingsHelper::siteName())
@if($search !== '' || $filterCount)
    @section('robots', 'noindex, follow')
@endif

@section('content')
<div class="max-w-7xl mx-auto px-4 py-5 md:py-8" x-data="{ filters: false }" @keydown.escape.window="filters = false">
    {{-- Heading --}}
    <div class="mb-4 flex items-center gap-3 md:mb-6">
        @if($activeBrand && $activeBrand->logo)
            <img src="{{ $activeBrand->logo }}" alt="" width="64" height="64" class="h-12 w-12 shrink-0 rounded-xl border border-gray-200 bg-white object-contain p-1 md:h-16 md:w-16">
        @endif
        <div class="min-w-0">
            <h1 class="break-words text-xl font-bold text-gray-900 md:text-2xl">{{ $heading }}</h1>
            <p class="mt-0.5 text-sm text-gray-500">{{ $products->total() }} টি পণ্য</p>
        </div>
    </div>

    {{-- Category cards (swipe on phones, a row on desktop); the one being shown is highlighted and tapping it again clears it --}}
    @if($categories->isNotEmpty())
        <div class="swipe-row relative -mx-4 mb-4 gap-3 px-4 pb-1 md:mx-0 md:mb-6 md:grid md:grid-cols-6 md:gap-4 md:overflow-visible md:px-0 lg:grid-cols-8" aria-label="ক্যাটাগরি"
            x-init="const a = $el.querySelector('[aria-current]'); if (a) $el.scrollLeft = a.offsetLeft - ($el.clientWidth - a.offsetWidth) / 2">
            @foreach($categories as $c)
                @php $on = $activeCategory?->id === $c->id; @endphp
                <x-category-card :category="$c" :active="$on" :href="$with(['category' => $on ? null : $c->slug])" class="swipe-half md:w-auto md:min-w-0 md:flex-none" />
            @endforeach
        </div>
    @endif
    <div class="mb-4 grid grid-cols-2 gap-2 md:hidden">
        <button type="button" @click="filters = true" aria-controls="filter-sheet" :aria-expanded="filters.toString()"
            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white text-sm font-semibold text-gray-700">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
            ফিল্টার
            @if($filterCount)
                <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-500 px-1 text-xs font-bold text-white">{{ $filterCount }}</span>
            @endif
        </button>
        <form method="GET" class="relative">
            @include('shop._hidden')
            <label for="sort-m" class="sr-only">সাজান</label>
            <select id="sort-m" name="sort" onchange="this.form.submit()"
                class="h-11 w-full appearance-none rounded-xl border border-gray-300 bg-white pl-10 pr-8 text-sm font-semibold text-gray-700 focus:border-brand-500 focus:outline-none">
                @foreach($sorts as $value => $label)
                    <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M7 4v16M3 16l4 4 4-4M17 20V4M13 8l4-4 4 4"/></svg>
            <noscript><button type="submit" class="mt-2 text-sm">সাজান</button></noscript>
        </form>
    </div>

    {{-- Ticked filters, each removable --}}
    @if($pills)
        <div class="mb-4 flex flex-wrap items-center gap-2" aria-label="চালু ফিল্টার">
            @foreach($pills as $pill)
                <a href="{{ $pill['url'] }}" class="inline-flex h-9 items-center gap-1.5 rounded-full bg-brand-50 pl-3.5 pr-2.5 text-sm font-medium text-brand-600 hover:bg-brand-100" aria-label="{{ $pill['label'] }} ফিল্টার সরান">
                    <span class="max-w-[14rem] truncate">{{ $pill['label'] }}</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </a>
            @endforeach
            @if(count($pills) > 1)
                <a href="{{ url()->current() }}" class="h-9 px-2 text-sm font-medium leading-9 text-gray-500 underline hover:text-gray-800">সব মুছুন</a>
            @endif
        </div>
    @endif

    <div class="grid gap-8 md:grid-cols-[240px_1fr]">
        {{-- Desktop sidebar: ticking a box updates the list straight away --}}
        <aside class="hidden md:block" aria-label="ফিল্টার">
            <form method="GET" action="{{ url()->current() }}" class="sticky top-36 max-h-[calc(100vh-10rem)] overflow-y-auto pr-1"
                x-data
                @change.debounce.350ms="$el.requestSubmit()"
                @submit="$el.querySelectorAll('input[type=number]').forEach(i => { if (! i.value) i.disabled = true })">
                @if($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
                @if($sortKey !== '')<input type="hidden" name="sort" value="{{ $sortKey }}">@endif
                @include('shop._filter-fields', ['idp' => 'd'])
                @if($filterCount)
                    <a href="{{ $clearFilters }}" class="mt-3 inline-flex min-h-10 items-center text-sm font-medium text-gray-500 underline hover:text-gray-800">ফিল্টার মুছুন</a>
                @endif
                <noscript><button type="submit" class="mt-3 h-10 w-full rounded-xl bg-brand-500 font-semibold text-white">প্রয়োগ করুন</button></noscript>
            </form>
        </aside>

        <div class="min-w-0">
            <div class="mb-4 hidden items-center justify-end md:flex">
                <form method="GET" class="flex items-center gap-2 text-sm">
                    @include('shop._hidden')
                    <label for="sort-d" class="text-gray-500">সাজান:</label>
                    <select id="sort-d" name="sort" onchange="this.form.submit()" class="h-10 rounded-xl border border-gray-300 bg-white px-3 text-sm focus:border-brand-500 focus:outline-none">
                        @foreach($sorts as $value => $label)
                            <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            @if($products->isEmpty())
                <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-brand-50 text-brand-500">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
                    </div>
                    <p class="text-lg font-semibold text-gray-800">কোনো পণ্য পাওয়া যায়নি</p>
                    <p class="mt-1 text-sm text-gray-500">
                        @if($search !== '') অন্য কোনো শব্দ দিয়ে খুঁজে দেখুন, বা বানান মিলিয়ে নিন। @else এই ফিল্টারে এখন কোনো পণ্য নেই — কয়েকটা ফিল্টার সরিয়ে দেখুন। @endif
                    </p>
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        @if($activeCount)
                            <a href="{{ url()->current() }}" class="inline-flex h-11 items-center rounded-xl border border-gray-300 px-5 text-sm font-semibold text-gray-700 hover:border-gray-400">ফিল্টার মুছুন</a>
                        @endif
                        <a href="{{ route('shop') }}" class="inline-flex h-11 items-center rounded-xl bg-brand-500 px-5 text-sm font-semibold text-white hover:bg-brand-600">সব পণ্য দেখুন</a>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                    @foreach($products as $p)
                        <x-product-card :product="$p" />
                    @endforeach
                </div>
                <div class="mt-8">{{ $products->links('partials.pagination') }}</div>
            @endif
        </div>
    </div>

    {{-- Phones: filter sheet — tick what you want, then "দেখুন" --}}
    <div id="filter-sheet" x-show="filters" x-cloak class="fixed inset-0 z-50 md:hidden" role="dialog" aria-modal="true" aria-labelledby="filter-sheet-title">
        <div class="absolute inset-0 bg-black/50" @click="filters = false" x-show="filters" x-transition.opacity></div>
        <form method="GET" action="{{ url()->current() }}"
            @submit="$el.querySelectorAll('input[type=number]').forEach(i => { if (! i.value) i.disabled = true })"
            class="absolute inset-x-0 bottom-0 flex max-h-[85dvh] flex-col rounded-t-3xl bg-white pb-[env(safe-area-inset-bottom)] shadow-2xl"
            x-show="filters" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
            x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
            @if($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
            @if($sortKey !== '')<input type="hidden" name="sort" value="{{ $sortKey }}">@endif
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                <h2 id="filter-sheet-title" class="text-lg font-bold">ফিল্টার</h2>
                <button type="button" @click="filters = false" aria-label="বন্ধ করুন" class="-mr-2 inline-flex h-10 w-10 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" stroke-linecap="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto px-5 py-3">
                @include('shop._filter-fields', ['idp' => 'm'])
            </div>
            <div class="grid grid-cols-2 gap-3 border-t border-gray-100 px-5 py-3">
                <a href="{{ $clearFilters }}" class="inline-flex h-12 items-center justify-center rounded-xl border border-gray-300 font-semibold text-gray-700">সব মুছুন</a>
                <button type="submit" class="inline-flex h-12 items-center justify-center rounded-xl bg-brand-500 font-semibold text-white">পণ্য দেখুন</button>
            </div>
        </form>
    </div>
</div>

{{-- Tracking: a search was made / a single category is being looked at (once per page load). --}}
@if(\App\Support\Tracking::enabled() && ($search !== '' || $activeCategory))
    @php
        $trackIds = $products->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
    @endphp
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.trackEvent) return;
            @if($search !== '')
                window.trackEvent('search', @json(['search_term' => $search, 'results' => $products->total(), 'content_ids' => $trackIds]));
            @endif
            @if($activeCategory)
                window.trackEvent('view_category', @json(['category_name' => $activeCategory->name, 'category_slug' => $activeCategory->slug, 'content_ids' => $trackIds]));
            @endif
        });
    </script>
@endif
@endsection
