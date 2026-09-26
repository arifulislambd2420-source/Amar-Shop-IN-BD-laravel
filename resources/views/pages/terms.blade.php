@extends('layouts.app')

@section('title', 'ব্যবহারের শর্তাবলী — '.config('site.name'))

@section('content')
<div class="max-w-4xl mx-auto px-4 py-10">
    <nav class="text-xs text-gray-500 mb-6">
        <a href="{{ url('/') }}" class="hover:text-orange-500">হোম</a> &gt; <span>ব্যবহারের শর্তাবলী</span>
    </nav>
    <div class="bg-white rounded-2xl border border-gray-200 p-6 md:p-10 shadow-sm space-y-6 text-gray-700 leading-relaxed">
        <h1 class="text-3xl font-bold text-gray-900 border-b border-gray-100 pb-4">ব্যবহারের শর্তাবলী (Terms of Service)</h1>
        <p><strong class="text-gray-900">{{ config('site.name') }}</strong> ওয়েবসাইট ব্যবহারের মাধ্যমে আপনি নিচের শর্তাবলীতে সম্মত হচ্ছেন।</p>
        <section class="space-y-3">
            <h2 class="text-xl font-bold text-gray-900">১. অর্ডার ও পেমেন্ট</h2>
            <p class="text-sm">সকল অর্ডার নিশ্চিত করার আগে আমরা ফোনে যোগাযোগ করতে পারি। বর্তমানে ক্যাশ অন ডেলিভারি (COD) একমাত্র সক্রিয় পেমেন্ট পদ্ধতি।</p>
        </section>
        <section class="space-y-3">
            <h2 class="text-xl font-bold text-gray-900">২. মূল্য ও পণ্যের তথ্য</h2>
            <p class="text-sm">ওয়েবসাইটে প্রদর্শিত মূল্য ও তথ্য যেকোনো সময় পরিবর্তনযোগ্য। ছবি শুধুমাত্র প্রদর্শনের জন্য, প্রকৃত পণ্যের সাথে সামান্য পার্থক্য থাকতে পারে।</p>
        </section>
        <section class="space-y-3">
            <h2 class="text-xl font-bold text-gray-900">৩. একাউন্ট দায়বদ্ধতা</h2>
            <p class="text-sm">আপনার একাউন্টের তথ্য ও পাসওয়ার্ড গোপন রাখার দায়িত্ব সম্পূর্ণরূপে আপনার। কোনো অস্বাভাবিক কার্যকলাপ দেখলে দ্রুত আমাদের জানান।</p>
        </section>
    </div>
</div>
@endsection
