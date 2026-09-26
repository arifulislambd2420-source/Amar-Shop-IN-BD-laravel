@extends('layouts.app')

@section('title', 'লগইন — '.config('site.name'))

@section('content')
<div class="max-w-md mx-auto px-4 py-12">
    <h1 class="text-2xl font-bold mb-6 text-center">লগইন করুন</h1>

    <form action="{{ route('customer.login.submit') }}" method="POST" class="flex flex-col gap-4 border border-gray-200 rounded-xl p-6 bg-white">
        @csrf
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">মোবাইল নম্বর <span class="text-orange-500">*</span></span>
            <input type="tel" name="phone" value="{{ old('phone') }}" required placeholder="01XXXXXXXXX" class="border border-gray-300 rounded-lg px-3 py-2">
        </label>
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">পাসওয়ার্ড <span class="text-orange-500">*</span></span>
            <input type="password" name="password" required class="border border-gray-300 rounded-lg px-3 py-2">
        </label>
        @error('phone') <p class="text-red-500 text-sm">{{ $message }}</p> @enderror
        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-3 rounded-lg">লগইন</button>
        <p class="text-center text-sm text-gray-500">একাউন্ট নেই? <a href="{{ route('customer.register') }}" class="text-orange-500 font-medium">রেজিস্টার করুন</a></p>
    </form>
</div>
@endsection
