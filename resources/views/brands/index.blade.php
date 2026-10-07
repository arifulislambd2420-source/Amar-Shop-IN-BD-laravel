@extends('layouts.app')

@section('title', 'ব্র্যান্ড সমূহ — '.\App\Support\SiteSettingsHelper::siteName())

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">ব্র্যান্ড সমূহ</h1>

    <form method="GET" class="mb-6 max-w-sm">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="ব্র্যান্ড খুঁজুন..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </form>

    @if($brands->isEmpty())
        <p class="text-gray-400">কোনো ব্র্যান্ড পাওয়া যায়নি।</p>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            @foreach($brands as $b)
                <a href="{{ route('shop', ['brand' => $b->id]) }}" class="bg-white border border-gray-200 rounded-xl p-6 flex flex-col items-center gap-3 text-center hover:border-brand-500 transition-colors">
                    @if($b->logo)
                        <img src="{{ $b->logo }}" alt="{{ $b->name }}" width="160" height="48" loading="lazy" class="h-12 object-contain">
                    @else
                        <span class="h-12 flex items-center text-xl font-bold text-gray-400">{{ $b->name }}</span>
                    @endif
                    <span class="font-medium">{{ $b->name }}</span>
                    <span class="text-xs text-gray-400">{{ $b->products_count }} টি পণ্য</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
