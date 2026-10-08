@extends('layouts.app')

@php
    // Page heading: what is being shown (brand / category / search / plain listing).
    $heading = $activeBrand->name ?? $activeCategory->name ?? ($search !== '' ? '“'.$search.'” এর ফলাফল' : $title);
    $sorts = ['newest' => 'নতুন আগে', 'price_asc' => 'দাম: কম থেকে বেশি', 'price_desc' => 'দাম: বেশি থেকে কম'];
    $sort = array_key_exists((string) request('sort'), $sorts) ? request('sort') : 'newest';
    // Links that change one filter and go back to page 1.
    $with = fn (array $q) => request()->fullUrlWithQuery($q + ['page' => null]);
    $filterCount = collect([$activeCategory, $activeBrand, $search !== '' ? $search : null])->filter()->count();
    $chip = 'inline-flex h-10 shrink-0 items-center rounded-full border px-4 text-sm font-medium transition-colors';
    $chipOn = 'border-brand-500 bg-brand-500 text-white';
    $chipOff = 'border-gray-200 bg-white text-gray-700 hover:border-gray-400';
@endphp

@section('title', $heading.' — '.\App\Support\SiteSettingsHelper::siteName())
@if($search !== '')
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

    {{-- Phones: category chips, then filter + sort --}}
    @if($categories->isNotEmpty())
        <div class="relative -mx-4 mb-3 flex gap-2 overflow-x-auto px-4 pb-1 scrollbar-none md:hidden" aria-label="ক্যাটাগরি"
            x-init="const a = $el.querySelector('[aria-current]'); if (a) $el.scrollLeft = a.offsetLeft - ($el.clientWidth - a.offsetWidth) / 2">
            <a href="{{ $with(['category' => null]) }}" class="{{ $chip }} {{ $activeCategory ? $chipOff : $chipOn }}">সব</a>
            @foreach($categories as $c)
                <a href="{{ $with(['category' => $c->slug]) }}" @if($activeCategory?->id === $c->id) aria-current="page" @endif
                    class="{{ $chip }} {{ $activeCategory?->id === $c->id ? $chipOn : $chipOff }}">{{ $c->name }}</a>
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
            @foreach(['category', 'brand', 'q'] as $key)
                @if(request($key))<input type="hidden" name="{{ $key }}" value="{{ request($key) }}">@endif
            @endforeach
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

    {{-- Active filters, removable --}}
    @if($filterCount)
        <div class="mb-4 flex flex-wrap items-center gap-2">
            @foreach(array_filter([
                'category' => $activeCategory?->name,
                'brand' => $activeBrand?->name,
                'q' => $search !== '' ? '“'.$search.'”' : null,
            ]) as $key => $label)
                <a href="{{ $with([$key => null]) }}" class="inline-flex h-9 items-center gap-1.5 rounded-full bg-brand-50 pl-3.5 pr-2.5 text-sm font-medium text-brand-600 hover:bg-brand-100" aria-label="{{ $label }} ফিল্টার সরান">
                    <span class="max-w-[14rem] truncate">{{ $label }}</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </a>
            @endforeach
            @if($filterCount > 1)
                <a href="{{ url()->current() }}" class="h-9 px-2 text-sm font-medium leading-9 text-gray-500 underline hover:text-gray-800">সব মুছুন</a>
            @endif
        </div>
    @endif

    <div class="grid gap-8 md:grid-cols-[220px_1fr]">
        {{-- Desktop sidebar --}}
        <aside class="hidden md:block" aria-label="ফিল্টার">
            <div class="sticky top-36 space-y-6">
                @foreach([
                    ['title' => 'ক্যাটাগরি', 'key' => 'category', 'items' => $categories->map(fn ($c) => ['value' => $c->slug, 'label' => $c->name, 'on' => $activeCategory?->id === $c->id]), 'any' => ! $activeCategory],
                    ['title' => 'ব্র্যান্ড', 'key' => 'brand', 'items' => $brands->map(fn ($b) => ['value' => $b->id, 'label' => $b->name, 'on' => $activeBrand?->id === $b->id]), 'any' => ! $activeBrand],
                ] as $group)
                    @continue($group['items']->isEmpty())
                    <div>
                        <h2 class="mb-2 text-sm font-semibold text-gray-900">{{ $group['title'] }}</h2>
                        <ul class="space-y-0.5 text-sm">
                            <li><a href="{{ $with([$group['key'] => null]) }}" @class(['block rounded-lg px-3 py-2', 'bg-brand-50 font-semibold text-brand-600' => $group['any'], 'text-gray-600 hover:bg-gray-100' => ! $group['any']])>সব</a></li>
                            @foreach($group['items'] as $item)
                                <li><a href="{{ $with([$group['key'] => $item['value']]) }}" @if($item['on']) aria-current="page" @endif
                                    @class(['block rounded-lg px-3 py-2', 'bg-brand-50 font-semibold text-brand-600' => $item['on'], 'text-gray-600 hover:bg-gray-100' => ! $item['on']])>{{ $item['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </aside>

        <div class="min-w-0">
            <div class="mb-4 hidden items-center justify-end md:flex">
                <form method="GET" class="flex items-center gap-2 text-sm">
                    @foreach(['category', 'brand', 'q'] as $key)
                        @if(request($key))<input type="hidden" name="{{ $key }}" value="{{ request($key) }}">@endif
                    @endforeach
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
                        @if($search !== '') অন্য কোনো শব্দ দিয়ে খুঁজে দেখুন, বা বানান মিলিয়ে নিন। @else এই ফিল্টারে এখন কোনো পণ্য নেই। @endif
                    </p>
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        @if($filterCount)
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

    {{-- Phones: filter sheet --}}
    <div id="filter-sheet" x-show="filters" x-cloak class="fixed inset-0 z-50 md:hidden" role="dialog" aria-modal="true" aria-labelledby="filter-sheet-title">
        <div class="absolute inset-0 bg-black/50" @click="filters = false" x-show="filters" x-transition.opacity></div>
        <div class="absolute inset-x-0 bottom-0 flex max-h-[85vh] flex-col rounded-t-3xl bg-white pb-[env(safe-area-inset-bottom)] shadow-2xl"
            x-show="filters" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
            x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                <h2 id="filter-sheet-title" class="text-lg font-bold">ফিল্টার</h2>
                <button type="button" @click="filters = false" aria-label="বন্ধ করুন" class="-mr-2 inline-flex h-10 w-10 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" stroke-linecap="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="flex-1 space-y-6 overflow-y-auto px-5 py-4">
                @if($categories->isNotEmpty())
                    <div>
                        <h3 class="mb-3 text-sm font-semibold text-gray-900">ক্যাটাগরি</h3>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ $with(['category' => null]) }}" class="{{ $chip }} {{ $activeCategory ? $chipOff : $chipOn }}">সব</a>
                            @foreach($categories as $c)
                                <a href="{{ $with(['category' => $c->slug]) }}" class="{{ $chip }} {{ $activeCategory?->id === $c->id ? $chipOn : $chipOff }}">{{ $c->name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if($brands->isNotEmpty())
                    <div>
                        <h3 class="mb-3 text-sm font-semibold text-gray-900">ব্র্যান্ড</h3>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ $with(['brand' => null]) }}" class="{{ $chip }} {{ $activeBrand ? $chipOff : $chipOn }}">সব</a>
                            @foreach($brands as $b)
                                <a href="{{ $with(['brand' => $b->id]) }}" class="{{ $chip }} {{ $activeBrand?->id === $b->id ? $chipOn : $chipOff }}">{{ $b->name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-3 border-t border-gray-100 px-5 py-3">
                <a href="{{ url()->current() }}" class="inline-flex h-12 items-center justify-center rounded-xl border border-gray-300 font-semibold text-gray-700">সব মুছুন</a>
                <button type="button" @click="filters = false" class="inline-flex h-12 items-center justify-center rounded-xl bg-brand-500 font-semibold text-white">দেখুন ({{ $products->total() }})</button>
            </div>
        </div>
    </div>
</div>
@endsection
