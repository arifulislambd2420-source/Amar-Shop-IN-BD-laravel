@extends('layouts.app')

@section('title', 'ডেলিভারি পলিসি — '.\App\Support\SiteSettingsHelper::siteName())

@section('content')
<div class="max-w-4xl mx-auto px-4 py-10">
    <nav class="text-xs text-gray-500 mb-6">
        <a href="{{ url('/') }}" class="hover:text-brand-500">হোম</a> &gt; <span>ডেলিভারি পলিসি</span>
    </nav>
    <div class="bg-white rounded-2xl border border-gray-200 p-6 md:p-10 shadow-sm space-y-6 text-gray-700 leading-relaxed">
        <h1 class="text-3xl font-bold text-ink border-b border-gray-100 pb-4">
            ডেলিভারি পলিসি ও চার্জ — <span class="text-brand-500">{{ \App\Support\SiteSettingsHelper::siteName() }}</span>
        </h1>
        <p>গ্রাহকদের কাছে দ্রুত এবং নিরাপদভাবে পণ্য পৌঁছানো আমাদের প্রধান দায়িত্ব। সারাদেশে কুরিয়ার সার্ভিসের মাধ্যমে হোম ডেলিভারি প্রদান করা হয়।</p>
        <div class="grid sm:grid-cols-2 gap-4 my-6">
            <div class="bg-brand-50 border border-brand-200 p-5 rounded-xl">
                @php
                    $dhakaFee = \App\Support\Delivery::dhakaFee();
                    $outsideFee = \App\Support\Delivery::outsideFee();
                    $freeMin = \App\Support\Delivery::freeMin();
                @endphp
                @if($dhakaFee == $outsideFee)
                    <h3 class="font-bold text-ink text-lg mb-2">🏙️ সারাদেশে স্ট্যান্ডার্ড ডেলিভারি চার্জ</h3>
                    <p class="text-2xl font-bold text-brand-500 mb-2">@taka($dhakaFee)</p>
                @else
                    <h3 class="font-bold text-ink text-lg mb-2">🏙️ ডেলিভারি চার্জ</h3>
                    <p class="text-lg font-bold text-brand-500">ঢাকার ভেতরে: @taka($dhakaFee)</p>
                    <p class="text-lg font-bold text-brand-500 mb-2">ঢাকার বাইরে: @taka($outsideFee)</p>
                @endif
                @if($freeMin)
                    <p class="text-sm font-semibold text-success-600 mb-2">@taka($freeMin) বা তার বেশি অর্ডারে ডেলিভারি ফ্রি!</p>
                @endif
                <p class="text-xs text-gray-600">ডেলিভারি সময়: সাধারণত ২৪ থেকে ৭২ ঘণ্টার মধ্যে।</p>
            </div>
            <div class="bg-blue-50 border border-blue-200 p-5 rounded-xl">
                <h3 class="font-bold text-ink text-lg mb-2">💵 পেমেন্ট পদ্ধতি</h3>
                <p class="text-lg font-bold text-blue-600 mb-2">Cash on Delivery</p>
                <p class="text-xs text-gray-600">পণ্য হাতে পেয়ে ডেলিভারি ম্যানকে মূল্য পরিশোধ করুন।</p>
            </div>
        </div>
        <section class="space-y-3">
            <h2 class="text-xl font-bold text-ink">ট্র্যাকিং</h2>
            <p class="text-sm">অর্ডার করার পর <a href="{{ route('track') }}" class="text-brand-500 font-medium">ট্র্যাক অর্ডার</a> পেজ থেকে আপনার ফোন নম্বর দিয়ে অর্ডারের সর্বশেষ অবস্থা জানতে পারবেন।</p>
        </section>
    </div>
</div>
@endsection
