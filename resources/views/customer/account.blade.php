@extends('layouts.app')

@section('title', 'আমার অ্যাকাউন্ট — '.\App\Support\SiteSettingsHelper::siteName())
@section('robots', 'noindex, nofollow')

@section('content')
@php
    $field = 'block h-12 w-full rounded-xl border border-gray-300 bg-white px-4 text-base placeholder:text-gray-400 focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100';
    $label = 'mb-1.5 block text-sm font-medium text-gray-700';
    $card = 'rounded-2xl border border-gray-200 bg-white p-4 sm:p-5';
@endphp
<div class="max-w-5xl mx-auto px-4 py-5 md:py-8">
    <div class="{{ $card }} mb-5 flex flex-wrap items-center justify-between gap-4">
        <div class="flex min-w-0 items-center gap-3">
            <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand-500 text-lg font-bold text-white">{{ mb_substr($user->name, 0, 1) }}</span>
            <div class="min-w-0">
                <h1 class="truncate text-lg font-bold text-gray-900 md:text-xl">{{ $user->name }}</h1>
                <p class="text-sm text-gray-500">{{ $user->phone }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('customer.logout') }}">
            @csrf
            <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-xl border border-gray-300 px-4 text-sm font-semibold text-gray-700 hover:border-gray-400">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                লগআউট
            </button>
        </form>
    </div>

    @if(session('status'))
        <div class="mb-5 rounded-2xl border border-success-200 bg-success-50 p-4 text-sm font-medium text-success-700" role="status">{{ session('status') }}</div>
    @endif

    <section id="orders" class="mb-8 scroll-mt-32" aria-labelledby="acc-orders">
        <h2 id="acc-orders" class="mb-3 text-lg font-bold">আমার অর্ডার</h2>

        @forelse($orders as $order)
            <a href="{{ route('customer.orders.show', $order->id) }}"
               class="mb-3 flex items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-brand-500">
                <div class="min-w-0">
                    <p class="truncate font-semibold text-gray-900">{{ $order->invoice_no }}</p>
                    <p class="text-xs text-gray-500">{{ $order->created_at->locale('bn')->translatedFormat('j F Y') }} · {{ $order->items->sum('quantity') }}টি পণ্য</p>
                    <span @class([
                        'mt-2 inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        'bg-error-50 text-error-500' => $order->status === 'cancelled',
                        'bg-success-50 text-success-700' => in_array($order->status, ['delivered', 'completed'], true),
                        'bg-brand-50 text-brand-600' => ! in_array($order->status, ['cancelled', 'delivered', 'completed'], true),
                    ])>{{ \App\Support\OrderStatus::label($order->status) }}</span>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <span class="font-bold text-brand-600 tabular-nums">@taka($order->total)</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="text-gray-400" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                </div>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center">
                <p class="font-semibold text-gray-800">এখনো কোনো অর্ডার নেই</p>
                <a href="{{ route('shop') }}" class="mt-4 inline-flex h-11 items-center rounded-xl bg-brand-500 px-5 text-sm font-semibold text-white hover:bg-brand-600">কেনাকাটা শুরু করুন</a>
            </div>
        @endforelse

        <div class="mt-4">{{ $orders->fragment('orders')->links('partials.pagination') }}</div>
    </section>

    <section id="addresses" class="scroll-mt-32" aria-labelledby="acc-addr">
        <h2 id="acc-addr" class="mb-3 text-lg font-bold">সেভ করা ঠিকানা</h2>

        @if($addresses->isNotEmpty())
            <div class="mb-5 grid gap-3 md:grid-cols-2">
                @foreach($addresses as $a)
                    <div class="rounded-2xl border-2 bg-white p-4 {{ $a->is_default ? 'border-brand-500' : 'border-gray-200' }}">
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <span class="font-semibold">{{ $a->label }}</span>
                            @if($a->is_default)
                                <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-600">ডিফল্ট</span>
                            @endif
                        </div>
                        <p class="text-sm">{{ $a->name }} · {{ $a->phone }}</p>
                        <p class="break-words text-sm text-gray-600">{{ $a->address }}, {{ $a->thana }}, {{ $a->district }}@if($a->postcode) - {{ $a->postcode }}@endif</p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            @unless($a->is_default)
                                <form method="POST" action="{{ route('customer.addresses.default', $a->id) }}">@csrf
                                    <button class="inline-flex h-11 items-center rounded-xl border border-gray-300 px-4 text-sm font-medium hover:border-brand-500">ডিফল্ট করুন</button>
                                </form>
                            @endunless
                            <form method="POST" action="{{ route('customer.addresses.destroy', $a->id) }}" onsubmit="return confirm('ঠিকানাটি মুছে ফেলবেন?')">@csrf @method('DELETE')
                                <button class="inline-flex h-11 items-center rounded-xl border border-error-200 px-4 text-sm font-medium text-error-500 hover:bg-error-50">মুছুন</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if($addresses->count() < 5)
            <details class="group rounded-2xl border border-gray-200 bg-white" @if($errors->address->any() || $addresses->isEmpty()) open @endif>
                <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between px-4 font-semibold sm:px-5">
                    + নতুন ঠিকানা যোগ করুন
                    <svg class="transition-transform group-open:rotate-180" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg>
                </summary>
                <form method="POST" action="{{ route('customer.addresses.store') }}" class="grid gap-4 border-t border-gray-100 p-4 sm:p-5 md:grid-cols-2">
                    @csrf
                    @if($errors->address->any())
                        <div class="rounded-xl border border-error-200 bg-error-50 p-3 text-sm text-error-500 md:col-span-2" role="alert">
                            @foreach($errors->address->all() as $message)<p>{{ $message }}</p>@endforeach
                        </div>
                    @endif
                    <div><label for="ad-label" class="{{ $label }}">লেবেল</label><input id="ad-label" class="{{ $field }}" name="label" value="{{ old('label', 'বাসা') }}" placeholder="বাসা / অফিস" required maxlength="60"></div>
                    <div><label for="ad-name" class="{{ $label }}">নাম</label><input id="ad-name" class="{{ $field }}" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required></div>
                    <div><label for="ad-phone" class="{{ $label }}">মোবাইল নম্বর</label><input id="ad-phone" type="tel" inputmode="numeric" class="{{ $field }}" name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel" required></div>
                    <div>
                        <label for="ad-district" class="{{ $label }}">জেলা</label>
                        <select id="ad-district" class="{{ $field }}" name="district" required>
                            <option value="">জেলা বাছাই করুন</option>
                            @foreach($districts as $d)
                                <option value="{{ $d }}" @selected(old('district') === $d)>{{ $d }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label for="ad-thana" class="{{ $label }}">থানা / উপজেলা</label><input id="ad-thana" class="{{ $field }}" name="thana" value="{{ old('thana') }}" required maxlength="100"></div>
                    <div><label for="ad-post" class="{{ $label }}">পোস্টকোড <span class="font-normal text-gray-400">(ঐচ্ছিক)</span></label><input id="ad-post" inputmode="numeric" class="{{ $field }}" name="postcode" value="{{ old('postcode') }}" maxlength="20"></div>
                    <div class="md:col-span-2">
                        <label for="ad-address" class="{{ $label }}">বিস্তারিত ঠিকানা</label>
                        <textarea id="ad-address" class="block w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-base focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100" name="address" rows="2" placeholder="বাসা/ফ্ল্যাট নং, রোড, এলাকা" required maxlength="1000">{{ old('address') }}</textarea>
                    </div>
                    <label class="flex min-h-11 items-center gap-3 text-sm md:col-span-2">
                        <input type="checkbox" name="is_default" value="1" class="h-5 w-5 accent-brand-500"> ডিফল্ট ঠিকানা করুন
                    </label>
                    <button class="inline-flex h-12 items-center justify-center rounded-xl bg-brand-500 px-6 font-semibold text-white hover:bg-brand-600 md:col-span-2 md:w-fit">ঠিকানা সেভ করুন</button>
                </form>
            </details>
        @endif
    </section>
</div>
@endsection
