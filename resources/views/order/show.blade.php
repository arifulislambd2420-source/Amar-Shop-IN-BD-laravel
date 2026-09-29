@extends('layouts.app')

@section('title', 'অর্ডার নিশ্চিতকরণ — '.config('site.name'))

@section('content')
@if(session('gtm_purchase'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.pushToDataLayer) {
            window.pushToDataLayer('purchase', { ecommerce: @json(session('gtm_purchase')) });
        }
    });
</script>
@endif
<div class="max-w-2xl mx-auto px-4 py-12">
    <div class="text-center mb-8">
        <div class="h-16 w-16 rounded-full bg-green-100 text-green-600 flex items-center justify-center mx-auto mb-4 text-3xl">✓</div>
        <h1 class="text-2xl font-bold mb-1">ধন্যবাদ! আপনার অর্ডার সফল হয়েছে</h1>
        <p class="text-gray-500">অর্ডার নম্বর: <span class="font-semibold text-gray-800">{{ $order->invoice_no }}</span></p>
    </div>

    <div class="border border-gray-200 rounded-xl p-5">
        <div class="flex justify-between items-center mb-4">
            <div class="font-semibold">অর্ডার বিবরণ</div>
            <span class="text-xs font-semibold bg-orange-100 text-orange-600 px-2 py-1 rounded">{{ ucfirst($order->status) }}</span>
        </div>
        <div class="flex flex-col gap-2 mb-3">
            @foreach($order->items as $item)
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">{{ $item->product_name }} × {{ $item->quantity }}</span>
                    <span>@taka($item->line_total)</span>
                </div>
            @endforeach
        </div>
        <div class="border-t border-gray-200 pt-3 space-y-1">
            <div class="flex justify-between text-sm"><span class="text-gray-600">সাবটোটাল</span><span>@taka($order->subtotal)</span></div>
            <div class="flex justify-between text-sm"><span class="text-gray-600">ডেলিভারি চার্জ</span><span>@taka($order->shipping_fee)</span></div>
            @if($order->discount > 0)
                <div class="flex justify-between text-sm"><span class="text-gray-600">ডিসকাউন্ট</span><span class="text-green-600">-@taka($order->discount)</span></div>
            @endif
            <div class="flex justify-between text-lg font-bold pt-2"><span>মোট</span><span class="text-orange-500">@taka($order->total)</span></div>
        </div>
    </div>

    <div class="border border-gray-200 rounded-xl p-5 mt-4 text-sm space-y-1">
        <div class="font-semibold mb-2">পেমেন্ট</div>
        @if ($order->payment_method === 'bkash')
            <p>বিকাশ —
                @if ($order->payment_status === 'paid')
                    <span class="text-green-600 font-semibold">পরিশোধিত</span>
                @else
                    <span class="text-red-600 font-semibold">অপরিশোধিত</span>
                @endif
            </p>
            @if ($order->payment_status === 'paid' && $order->transaction_id)
                <p class="text-gray-500">Transaction ID: {{ $order->transaction_id }}</p>
            @endif
            @if ($order->status === 'on_hold')
                <p class="text-orange-600">আপনার পেমেন্ট পেয়েছি, তবে একটি পণ্যের স্টক নিয়ে সমস্যা হয়েছে — আমরা শীঘ্রই আপনার সাথে যোগাযোগ করব।</p>
            @endif
        @else
            <p>ক্যাশ অন ডেলিভারি</p>
        @endif
    </div>

    <div class="border border-gray-200 rounded-xl p-5 mt-4 text-sm space-y-1">
        <div class="font-semibold mb-2">ডেলিভারি ঠিকানা</div>
        <p>{{ $order->customer_name }} — {{ $order->phone }}</p>
        <p>{{ $order->address }}, {{ $order->thana }}, {{ $order->district }} {{ $order->postcode }}</p>
    </div>

    <div class="text-center mt-8 flex flex-col sm:flex-row gap-3 justify-center items-center">
        <a href="{{ route('order.invoice', $order->order_token) }}" target="_blank"
           class="inline-block bg-brand-navy text-white font-semibold px-5 py-2.5 rounded-lg">
            🧾 ইনভয়েস ডাউনলোড
        </a>
        <a href="{{ route('shop') }}" class="text-orange-500 font-semibold">শপিং চালিয়ে যান →</a>
    </div>
</div>
@endsection
