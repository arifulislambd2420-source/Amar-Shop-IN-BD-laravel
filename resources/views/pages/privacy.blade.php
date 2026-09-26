@extends('layouts.app')

@section('title', 'প্রাইভেসি পলিসি — '.config('site.name'))

@section('content')
<div class="max-w-4xl mx-auto px-4 py-10">
    <nav class="text-xs text-gray-500 mb-6">
        <a href="{{ url('/') }}" class="hover:text-orange-500">হোম</a> &gt; <span>প্রাইভেসি পলিসি</span>
    </nav>
    <div class="bg-white rounded-2xl border border-gray-200 p-6 md:p-10 shadow-sm space-y-6 text-gray-700 leading-relaxed">
        <h1 class="text-3xl font-bold text-gray-900 border-b border-gray-100 pb-4">প্রাইভেসি পলিসি (Privacy Policy)</h1>
        <p>
            <strong class="text-gray-900">{{ config('site.legal_name') }}</strong> গ্রাহকদের ব্যক্তিগত তথ্যের সর্বোচ্চ সুরক্ষা ও গোপনীয়তা নিশ্চিত করতে প্রতিশ্রুতিবদ্ধ। আমাদের ওয়েবসাইট ব্যবহারের মাধ্যমে আপনি আমাদের গোপনীয়তা নীতি মেনে নিচ্ছেন।
        </p>
        <section class="space-y-3">
            <h2 class="text-xl font-bold text-gray-900">১. আমরা কী কী তথ্য সংগ্রহ করি</h2>
            <ul class="list-disc list-inside space-y-1 text-sm pl-2">
                <li>অর্ডার সম্পন্ন করার জন্য আপনার নাম, মোবাইল নম্বর, ডেলিভারির সম্পূর্ণ ঠিকানা।</li>
                <li>কাস্টমার একাউন্ট বা সাপোর্টের জন্য প্রয়োজনীয় ইমেইল ঠিকানা (ঐচ্ছিক)।</li>
                <li>ওয়েবসাইটের পারফরম্যান্স ও অভিজ্ঞতা বৃদ্ধির জন্য স্ট্যান্ডার্ড ব্রাউজিং ডেটা।</li>
            </ul>
        </section>
        <section class="space-y-3">
            <h2 class="text-xl font-bold text-gray-900">২. তথ্যের ব্যবহার</h2>
            <p class="text-sm">সংগৃহীত তথ্য শুধুমাত্র পণ্য ডেলিভারি, অর্ডারের স্ট্যাটাস আপডেট জানানো, কাস্টমার সাপোর্ট প্রদান এবং আমাদের সেবার মান উন্নত করার কাজে ব্যবহার করা হয়। আপনার তথ্য কখনো তৃতীয় পক্ষের কাছে বিক্রি করা হয় না।</p>
        </section>
        <section class="space-y-3">
            <h2 class="text-xl font-bold text-gray-900">৩. তথ্য সুরক্ষা</h2>
            <p class="text-sm">আপনার পাসওয়ার্ড এনক্রিপ্টেড (hashed) অবস্থায় সংরক্ষণ করা হয় এবং কোনো ভাবেই প্লেইন টেক্সটে দেখানো বা রিটার্ন করা হয় না।</p>
        </section>
    </div>
</div>
@endsection
