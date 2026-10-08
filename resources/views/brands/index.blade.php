@extends('layouts.app')

@section('title', 'ব্র্যান্ড সমূহ — '.\App\Support\SiteSettingsHelper::siteName())

@section('content')
<div class="max-w-7xl mx-auto px-4 py-5 md:py-8">
    <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900 md:text-2xl">ব্র্যান্ড সমূহ</h1>
            <p class="mt-0.5 text-sm text-gray-500">{{ $brands->count() }} টি ব্র্যান্ড</p>
        </div>
        <form method="GET" role="search" class="flex h-11 w-full sm:max-w-sm">
            <label for="brand-q" class="sr-only">ব্র্যান্ড খুঁজুন</label>
            <input id="brand-q" type="search" name="q" value="{{ request('q') }}" placeholder="ব্র্যান্ড খুঁজুন..."
                class="w-full min-w-0 rounded-l-xl border border-gray-300 bg-white px-4 text-base focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100 sm:text-sm">
            <button type="submit" class="inline-flex shrink-0 items-center justify-center rounded-r-xl bg-brand-500 px-4 text-white hover:bg-brand-600" aria-label="সার্চ">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            </button>
        </form>
    </div>

    @if($brands->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center">
            <p class="text-lg font-semibold text-gray-800">কোনো ব্র্যান্ড পাওয়া যায়নি</p>
            @if(request('q'))
                <a href="{{ route('brands') }}" class="mt-4 inline-flex h-11 items-center rounded-xl border border-gray-300 px-5 text-sm font-semibold text-gray-700">সব ব্র্যান্ড দেখুন</a>
            @endif
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 md:grid-cols-4 lg:grid-cols-5">
            @foreach($brands as $b)
                <a href="{{ route('shop', ['brand' => $b->id]) }}" class="flex min-w-0 flex-col items-center gap-2 rounded-2xl border border-gray-200 bg-white p-4 text-center transition hover:border-brand-500 hover:shadow-sm sm:p-5">
                    <span class="flex h-16 w-full items-center justify-center">
                        @if($b->logo)
                            <img src="{{ $b->logo }}" alt="" width="160" height="64" loading="lazy" class="max-h-16 max-w-full object-contain">
                        @else
                            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-brand-50 text-xl font-bold text-brand-600">{{ mb_substr($b->name, 0, 1) }}</span>
                        @endif
                    </span>
                    <span class="w-full truncate font-semibold text-gray-800">{{ $b->name }}</span>
                    <span class="text-xs text-gray-500">{{ $b->products_count }} টি পণ্য</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
