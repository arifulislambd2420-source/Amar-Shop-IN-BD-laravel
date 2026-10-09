@extends('layouts.app')

@section('title', 'অর্ডার '.$order->invoice_no.' — '.\App\Support\SiteSettingsHelper::siteName())
@section('robots', 'noindex, nofollow')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-5 md:py-8">
    <a href="{{ route('customer.account') }}#orders" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand-600">← আমার অর্ডার</a>

    <div class="mt-3 mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="break-all text-xl font-bold md:text-2xl">অর্ডার {{ $order->invoice_no }}</h1>
            <p class="text-sm text-gray-500">{{ $order->created_at->locale('bn')->translatedFormat('j F Y, g:i A') }}</p>
        </div>
        <span class="rounded bg-brand-100 px-3 py-1 text-sm font-semibold text-brand-600">{{ \App\Support\OrderStatus::label($order->status) }}</span>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5">
        <div class="space-y-2">
            @foreach($order->items as $item)
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">{{ $item->product_name }} × {{ $item->quantity }}</span>
                    <span>@taka($item->line_total)</span>
                </div>
            @endforeach
        </div>
        <div class="mt-3 space-y-1 border-t border-gray-200 pt-3 text-sm">
            <div class="flex justify-between"><span class="text-gray-600">সাবটোটাল</span><span>@taka($order->subtotal)</span></div>
            <div class="flex justify-between"><span class="text-gray-600">ডেলিভারি চার্জ</span><span>@taka($order->shipping_fee)</span></div>
            @if($order->discount > 0)
                <div class="flex justify-between"><span class="text-gray-600">ডিসকাউন্ট</span><span class="text-success-600">-@taka($order->discount)</span></div>
            @endif
            <div class="flex justify-between border-t border-gray-200 pt-2 text-lg font-bold"><span>মোট</span><span class="text-brand-600">@taka($order->total)</span></div>
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-gray-200 bg-white p-5 text-sm">
        <div class="mb-1 font-semibold">ডেলিভারি তথ্য</div>
        <p>{{ $order->customer_name }} · {{ $order->phone }}</p>
        <p class="text-gray-600">{{ $order->address }}@if($order->thana && $order->thana !== 'N/A'), {{ $order->thana }}@endif, {{ $order->district }}</p>
        <p class="mt-2 text-gray-500">পেমেন্ট: {{ $order->payment_method === 'bkash' ? 'বিকাশ' : 'ক্যাশ অন ডেলিভারি' }}</p>
    </div>

    <div class="mt-4 flex flex-wrap gap-3">
        <a href="{{ route('order.invoice', $order->order_token) }}" target="_blank" class="inline-flex h-11 items-center rounded-xl bg-secondary px-5 text-sm font-semibold text-white">ইনভয়েস দেখুন</a>
        <a href="{{ route('track') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-300 px-5 text-sm font-semibold hover:border-brand-500">ট্র্যাক করুন</a>
    </div>
</div>
@endsection
