@extends('layouts.app')

@section('title', 'আমার অ্যাকাউন্ট — '.\App\Support\SiteSettingsHelper::siteName())
@section('robots', 'noindex, nofollow')

@section('content')
@php
    $field = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-400/30';
@endphp
<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">আমার অ্যাকাউন্ট</h1>
            <p class="text-sm text-gray-500">{{ $user->name }} · {{ $user->phone }}</p>
        </div>
    </div>

    @if(session('status'))
        <div class="mb-6 rounded-lg border border-success-200 bg-success-50 p-3 text-sm text-success-700">{{ session('status') }}</div>
    @endif

    <section id="orders" class="mb-10">
        <h2 class="mb-3 text-lg font-bold">আমার অর্ডার</h2>

        @forelse($orders as $order)
            <a href="{{ route('customer.orders.show', $order->id) }}"
               class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-4 hover:border-brand-500">
                <div>
                    <div class="font-semibold">{{ $order->invoice_no }}</div>
                    <div class="text-xs text-gray-500">{{ $order->created_at->format('d M Y, h:i A') }} · {{ $order->items->sum('quantity') }} টি পণ্য</div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="rounded bg-brand-100 px-2 py-1 text-xs font-semibold text-brand-600">{{ \App\Support\OrderStatus::label($order->status) }}</span>
                    <span class="font-bold text-brand-600">@taka($order->total)</span>
                </div>
            </a>
        @empty
            <p class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-gray-500">
                এখনো কোনো অর্ডার নেই। <a href="{{ route('shop') }}" class="text-brand-500 underline">শপিং শুরু করুন</a>
            </p>
        @endforelse

        {{ $orders->fragment('orders')->links() }}
    </section>

    <section id="addresses">
        <h2 class="mb-3 text-lg font-bold">সেভ করা ঠিকানা</h2>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach($addresses as $a)
                <div class="rounded-xl border bg-white p-4 {{ $a->is_default ? 'border-brand-500' : 'border-gray-200' }}">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="font-semibold">{{ $a->label }}</span>
                        @if($a->is_default)
                            <span class="rounded bg-brand-100 px-2 py-0.5 text-xs font-semibold text-brand-600">ডিফল্ট</span>
                        @endif
                    </div>
                    <p class="text-sm">{{ $a->name }} · {{ $a->phone }}</p>
                    <p class="text-sm text-gray-600">{{ $a->address }}, {{ $a->thana }}, {{ $a->district }}@if($a->postcode) - {{ $a->postcode }}@endif</p>

                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        @unless($a->is_default)
                            <form method="POST" action="{{ route('customer.addresses.default', $a->id) }}">@csrf
                                <button class="rounded border border-gray-300 px-3 py-1.5 hover:border-brand-500">ডিফল্ট করুন</button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('customer.addresses.destroy', $a->id) }}" onsubmit="return confirm('ঠিকানাটি মুছে ফেলবেন?')">@csrf @method('DELETE')
                            <button class="rounded border border-error-200 px-3 py-1.5 text-error-600 hover:bg-error-50">মুছুন</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        @if($addresses->count() < 5)
            <form method="POST" action="{{ route('customer.addresses.store') }}" class="mt-5 grid gap-3 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-2">
                @csrf
                <h3 class="font-semibold md:col-span-2">নতুন ঠিকানা যোগ করুন</h3>
                @if($errors->address->any())
                    <div class="rounded-lg border border-error-200 bg-error-50 p-3 text-sm text-error-600 md:col-span-2">
                        @foreach($errors->address->all() as $message)<div>{{ $message }}</div>@endforeach
                    </div>
                @endif
                <input class="{{ $field }}" name="label" value="{{ old('label', 'বাসা') }}" placeholder="লেবেল (বাসা / অফিস)" required>
                <input class="{{ $field }}" name="name" value="{{ old('name', $user->name) }}" placeholder="নাম" required>
                <input class="{{ $field }}" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="ফোন নম্বর" required>
                <select class="{{ $field }}" name="district" required>
                    <option value="">জেলা</option>
                    @foreach($districts as $d)
                        <option value="{{ $d }}" @selected(old('district') === $d)>{{ $d }}</option>
                    @endforeach
                </select>
                <input class="{{ $field }}" name="thana" value="{{ old('thana') }}" placeholder="থানা / উপজেলা" required>
                <input class="{{ $field }}" name="postcode" value="{{ old('postcode') }}" placeholder="পোস্টকোড (ঐচ্ছিক)">
                <textarea class="{{ $field }} md:col-span-2" name="address" rows="2" placeholder="বিস্তারিত ঠিকানা" required>{{ old('address') }}</textarea>
                <label class="flex items-center gap-2 text-sm md:col-span-2">
                    <input type="checkbox" name="is_default" value="1" class="accent-brand-500"> ডিফল্ট ঠিকানা করুন
                </label>
                <button class="rounded-lg bg-brand-500 px-5 py-2.5 font-semibold text-white hover:bg-brand-600 md:col-span-2 md:w-fit">ঠিকানা সেভ করুন</button>
            </form>
        @endif
    </section>
</div>
@endsection
