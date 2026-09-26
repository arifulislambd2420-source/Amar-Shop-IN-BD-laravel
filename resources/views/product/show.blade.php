@extends('layouts.app')

@section('title', ($product->seo_title ?: $product->name).' — '.config('site.name'))
@section('description', $product->meta_description ?: \Illuminate\Support\Str::limit(strip_tags($product->description), 160))

@section('content')
@php
    $onSale = $product->hasDiscount();
    $price = $product->displayPrice();
@endphp
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.pushToDataLayer) {
            window.pushToDataLayer('view_item', {
                ecommerce: {
                    currency: 'BDT',
                    value: {{ (float) $price }},
                    items: [{
                        item_id: {{ $product->id }},
                        item_name: @json($product->name),
                        price: {{ (float) $price }},
                        quantity: 1,
                    }],
                },
            });
        }
    });
</script>
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="grid md:grid-cols-2 gap-8">
        <div class="relative aspect-square bg-gray-50 rounded-xl overflow-hidden">
            @if($product->image)
                <img src="{{ $product->image }}" alt="{{ $product->name }}" class="absolute inset-0 h-full w-full object-cover">
            @endif
        </div>
        <div>
            <h1 class="text-2xl font-bold mb-2">{{ $product->name }}</h1>
            <div class="flex items-baseline gap-3 mb-4">
                <span class="text-2xl font-bold text-orange-500">@taka($price)</span>
                @if($onSale)
                    <span class="text-gray-400 line-through">@taka($product->price)</span>
                @endif
            </div>
            <p class="text-gray-600 mb-6 whitespace-pre-line">{{ $product->description }}</p>
            <p class="text-sm text-gray-500 mb-4">
                স্টক: {{ $product->stock > 0 ? "{$product->stock} টি আছে" : 'স্টক নেই' }}
            </p>
            <div class="max-w-xs">
                @livewire('product.add-to-cart', ['productId' => $product->id, 'mode' => 'detail'])
            </div>
        </div>
    </div>

    <div class="mt-12 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold">রিভিউ ও রেটিং ({{ $product->reviews->count() }})</h2>
            <button type="button" onclick="document.getElementById('review-form').classList.toggle('hidden')" class="bg-orange-500 text-white px-4 py-2 rounded font-medium hover:bg-orange-600">রিভিউ লিখুন</button>
        </div>

        @if(session('status'))
            <div class="mb-6 p-4 bg-green-50 text-green-700 border border-green-200 rounded">{{ session('status') }}</div>
        @endif

        <form id="review-form" action="{{ route('product.reviews.store', $product->slug) }}" method="POST" class="hidden mb-8 p-4 bg-gray-50 border border-gray-200 rounded-lg">
            @csrf
            <h3 class="font-semibold mb-4">আপনার রিভিউ লিখুন</h3>
            <div class="grid md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">আপনার নাম <span class="text-red-500">*</span></label>
                    <input type="text" name="customer_name" required class="w-full border border-gray-300 p-2 rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">রেটিং <span class="text-red-500">*</span></label>
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
            <button type="submit" class="bg-orange-500 text-white px-4 py-2 rounded font-medium hover:bg-orange-600">সাবমিট করুন</button>
        </form>

        <div class="space-y-4">
            @forelse($product->reviews as $review)
                <div class="border-b border-gray-100 pb-4">
                    <div class="flex items-center justify-between">
                        <span class="font-medium">{{ $review->customer_name }}</span>
                        <span class="text-orange-500 text-sm">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
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
            <h2 class="text-2xl font-bold mb-6">সিমিলার প্রোডাক্ট</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                @foreach($similarProducts as $p)
                    <x-product-card :product="$p" />
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
