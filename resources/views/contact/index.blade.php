@extends('layouts.app')

@section('title', 'যোগাযোগ — '.config('site.name'))

@section('content')
<div class="max-w-2xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold mb-1 text-center">যোগাযোগ করুন</h1>
    <p class="text-gray-500 text-center mb-8">আপনার যেকোনো প্রশ্ন বা মতামত আমাদের জানান</p>

    @if(session('status'))
        <div class="mb-6 p-4 bg-green-50 text-green-700 border border-green-200 rounded">{{ session('status') }}</div>
    @endif

    <form action="{{ route('contact.store') }}" method="POST" class="flex flex-col gap-4 border border-gray-200 rounded-xl p-6 bg-white">
        @csrf
        <div class="grid sm:grid-cols-2 gap-4">
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700">নাম <span class="text-orange-500">*</span></span>
                <input type="text" name="name" value="{{ old('name') }}" required class="border border-gray-300 rounded-lg px-3 py-2">
                @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700">ফোন নম্বর <span class="text-orange-500">*</span></span>
                <input type="text" name="phone" value="{{ old('phone') }}" required class="border border-gray-300 rounded-lg px-3 py-2">
                @error('phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </label>
        </div>
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">ইমেইল (ঐচ্ছিক)</span>
            <input type="email" name="email" value="{{ old('email') }}" class="border border-gray-300 rounded-lg px-3 py-2">
            @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </label>
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">বিষয় <span class="text-orange-500">*</span></span>
            <input type="text" name="subject" value="{{ old('subject') }}" required class="border border-gray-300 rounded-lg px-3 py-2">
            @error('subject') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </label>
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">বার্তা <span class="text-orange-500">*</span></span>
            <textarea name="message" rows="5" required class="border border-gray-300 rounded-lg px-3 py-2">{{ old('message') }}</textarea>
            @error('message') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </label>
        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-3 rounded-lg">পাঠিয়ে দিন</button>
    </form>
</div>
@endsection
