@extends('layouts.app')

@section('title', 'অর্ডার নিশ্চিতকরণ — '.\App\Support\SiteSettingsHelper::siteName())
@section('robots', 'noindex, nofollow')

@section('content')
@if(! empty($purchase))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.trackEvent) {
            window.trackEvent('purchase', @json($purchase['ecommerce']), @json($purchase['event_id']));
        }
    });
</script>
@endif
<div class="max-w-2xl mx-auto px-4 py-6 md:py-12">
    <div class="mb-6 text-center">
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-success-50 text-success-600">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>
        </div>
        <h1 class="text-xl font-bold text-gray-900 md:text-2xl">ধন্যবাদ! আপনার অর্ডার সফল হয়েছে</h1>
        <p class="mt-1 text-gray-600">অর্ডার নম্বর: <span class="font-bold text-gray-900">{{ $order->invoice_no }}</span></p>
        @if($order->payment_method !== 'bkash')
            <p class="mx-auto mt-2 max-w-md text-sm text-gray-500">শিগগিরই আমরা <span class="font-medium text-gray-700">{{ $order->phone }}</span> নম্বরে কল করে অর্ডারটি নিশ্চিত করব।</p>
        @endif
    </div>

    <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5">
        <div class="mb-4 flex items-center justify-between gap-2">
            <h2 class="font-semibold">অর্ডার বিবরণ</h2>
            <span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-600">{{ \App\Support\OrderStatus::label($order->status) }}</span>
        </div>
        <ul class="space-y-2 text-sm">
            @foreach($order->items as $item)
                <li class="flex justify-between gap-3"><span class="min-w-0 text-gray-600">{{ $item->product_name }} × {{ $item->quantity }}</span><span class="shrink-0 tabular-nums">@taka($item->line_total)</span></li>
            @endforeach
        </ul>
        <dl class="mt-3 space-y-1.5 border-t border-gray-100 pt-3 text-sm">
            <div class="flex justify-between"><dt class="text-gray-600">সাবটোটাল</dt><dd class="tabular-nums">@taka($order->subtotal)</dd></div>
            <div class="flex justify-between"><dt class="text-gray-600">ডেলিভারি চার্জ</dt><dd class="tabular-nums">@if($order->shipping_fee == 0)<span class="text-success-600">ফ্রি</span>@else @taka($order->shipping_fee) @endif</dd></div>
            @if($order->discount > 0)
                <div class="flex justify-between"><dt class="text-gray-600">ছাড়</dt><dd class="text-success-600 tabular-nums">−@taka($order->discount)</dd></div>
            @endif
            <div class="flex justify-between pt-2 text-lg font-bold"><dt>মোট</dt><dd class="text-brand-600 tabular-nums">@taka($order->total)</dd></div>
        </dl>
    </section>

    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <section class="rounded-2xl border border-gray-200 bg-white p-4 text-sm sm:p-5">
            <h2 class="mb-2 font-semibold">পেমেন্ট</h2>
            @if ($order->payment_method === 'bkash')
                <p>বিকাশ —
                    @if ($order->payment_status === 'paid')
                        <span class="font-semibold text-success-600">পরিশোধিত</span>
                    @else
                        <span class="font-semibold text-error-500">অপরিশোধিত</span>
                    @endif
                </p>
                @if ($order->payment_status === 'paid' && $order->transaction_id)
                    <p class="mt-1 break-all text-gray-500">Transaction ID: {{ $order->transaction_id }}</p>
                @endif
                @if ($order->status === 'on_hold')
                    <p class="mt-2 text-brand-600">আপনার পেমেন্ট পেয়েছি, তবে একটি পণ্যের স্টক নিয়ে সমস্যা হয়েছে — আমরা শীঘ্রই আপনার সাথে যোগাযোগ করব।</p>
                @endif
            @else
                <p>ক্যাশ অন ডেলিভারি — পণ্য হাতে পেয়ে <span class="font-semibold">@taka($order->total)</span> দিন।</p>
            @endif
        </section>
        <section class="rounded-2xl border border-gray-200 bg-white p-4 text-sm sm:p-5">
            <h2 class="mb-2 font-semibold">ডেলিভারি ঠিকানা</h2>
            <p class="font-medium text-gray-800">{{ $order->customer_name }} — {{ $order->phone }}</p>
            <p class="mt-1 break-words text-gray-600">{{ $order->address }}, {{ $order->thana }}, {{ $order->district }} {{ $order->postcode }}</p>
        </section>
    </div>

    <div class="mt-6 grid gap-3 sm:grid-cols-3">
        <a href="{{ route('track') }}" class="inline-flex h-12 items-center justify-center rounded-xl bg-brand-500 font-semibold text-white hover:bg-brand-600">অর্ডার ট্র্যাক করুন</a>
        <a href="{{ route('order.invoice', $order->order_token) }}" target="_blank" rel="noopener" class="inline-flex h-12 items-center justify-center rounded-xl border-2 border-gray-300 font-semibold text-gray-700 hover:border-gray-400">ইনভয়েস দেখুন</a>
        <a href="{{ route('shop') }}" class="inline-flex h-12 items-center justify-center rounded-xl font-semibold text-brand-600 hover:bg-brand-50">কেনাকাটা চালিয়ে যান →</a>
    </div>
</div>
@endsection
