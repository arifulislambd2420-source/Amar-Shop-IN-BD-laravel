@extends('layouts.app')

@section('title', $title.' — '.\App\Support\SiteSettingsHelper::siteName())

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    @if(! empty($activeBrand))
        <div class="mb-6 flex items-center gap-4">
            @if($activeBrand->logo)
                <img src="{{ $activeBrand->logo }}" alt="{{ $activeBrand->name }}" width="96" height="96" class="h-16 w-16 rounded-lg border border-gray-200 bg-white object-contain p-1">
            @endif
            <h1 class="text-2xl font-bold">{{ $activeBrand->name }}</h1>
        </div>
    @else
        <h1 class="text-2xl font-bold mb-6">{{ $title }}</h1>
    @endif

    <div class="grid md:grid-cols-[220px_1fr] gap-8">
        <aside class="space-y-6">
            <form method="GET" class="space-y-6">
                @if(request('q'))
                    <input type="hidden" name="q" value="{{ request('q') }}">
                @endif

                <div>
                    <div class="font-semibold text-sm mb-2">ক্যাটাগরি</div>
                    <ul class="space-y-1 text-sm">
                        <li><a href="{{ request()->fullUrlWithQuery(['category' => null]) }}" class="{{ ! request('category') ? 'text-brand-500 font-medium' : 'text-gray-600' }}">সব</a></li>
                        @foreach($categories as $c)
                            <li><a href="{{ request()->fullUrlWithQuery(['category' => $c->slug]) }}" class="{{ request('category') === $c->slug ? 'text-brand-500 font-medium' : 'text-gray-600' }}">{{ $c->name }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <div class="font-semibold text-sm mb-2">ব্র্যান্ড</div>
                    <ul class="space-y-1 text-sm">
                        <li><a href="{{ request()->fullUrlWithQuery(['brand' => null]) }}" class="{{ ! request('brand') ? 'text-brand-500 font-medium' : 'text-gray-600' }}">সব</a></li>
                        @foreach($brands as $b)
                            <li><a href="{{ request()->fullUrlWithQuery(['brand' => $b->id]) }}" class="{{ (string) request('brand') === (string) $b->id ? 'text-brand-500 font-medium' : 'text-gray-600' }}">{{ $b->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </form>
        </aside>

        <div>
            <div class="flex justify-end mb-4">
                <form method="GET" class="flex items-center gap-2 text-sm">
                    @foreach(['category', 'brand', 'q'] as $key)
                        @if(request($key))
                            <input type="hidden" name="{{ $key }}" value="{{ request($key) }}">
                        @endif
                    @endforeach
                    <label class="text-gray-500">সাজান:</label>
                    <select name="sort" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-2 py-1.5">
                        <option value="newest" @selected(request('sort', 'newest') === 'newest')>নতুন আগে</option>
                        <option value="price_asc" @selected(request('sort') === 'price_asc')>দাম: কম থেকে বেশি</option>
                        <option value="price_desc" @selected(request('sort') === 'price_desc')>দাম: বেশি থেকে কম</option>
                    </select>
                </form>
            </div>

            @if($products->isEmpty())
                <p class="text-center text-gray-400 py-16">কোনো পণ্য পাওয়া যায়নি।</p>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach($products as $p)
                        <x-product-card :product="$p" />
                    @endforeach
                </div>
                <div class="mt-8">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
