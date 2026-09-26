@extends('layouts.app')

@section('title', 'রেজিস্টার — '.config('site.name'))

@section('content')
<div class="max-w-md mx-auto px-4 py-12">
    <h1 class="text-2xl font-bold mb-6 text-center">Register করুন</h1>

    <form action="{{ route('customer.register.submit') }}" method="POST" class="flex flex-col gap-4 border border-gray-200 rounded-xl p-6 bg-white">
        @csrf
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">পূর্ণ নাম <span class="text-orange-500">*</span></span>
            <input type="text" name="name" value="{{ old('name') }}" required class="border border-gray-300 rounded-lg px-3 py-2">
            @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </label>
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">মোবাইল নম্বর <span class="text-orange-500">*</span></span>
            <input type="tel" name="phone" value="{{ old('phone') }}" required placeholder="01XXXXXXXXX" class="border border-gray-300 rounded-lg px-3 py-2">
            @error('phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </label>
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">পাসওয়ার্ড <span class="text-orange-500">*</span></span>
            <input type="password" name="password" required class="border border-gray-300 rounded-lg px-3 py-2">
            <span class="text-xs text-gray-400">কমপক্ষে ৬ অক্ষরের হতে হবে</span>
            @error('password') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </label>
        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-3 rounded-lg">রেজিস্টার করুন</button>
        <p class="text-center text-sm text-gray-500">ইতিমধ্যে একাউন্ট আছে? <a href="{{ route('customer.login') }}" class="text-orange-500 font-medium">লগইন করুন</a></p>
    </form>
</div>
@endsection
