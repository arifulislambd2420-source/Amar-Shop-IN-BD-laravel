@extends('layouts.app')

@section('title', 'আমাদের সম্পর্কে — '.\App\Support\SiteSettingsHelper::siteName())

@section('content')
<div class="max-w-4xl mx-auto px-4 py-10">
    <nav class="text-xs text-gray-500 mb-6">
        <a href="{{ url('/') }}" class="hover:text-brand-500">হোম</a> &gt; <span>আমাদের সম্পর্কে</span>
    </nav>
    <div class="bg-white rounded-2xl border border-gray-200 p-6 md:p-10 shadow-sm space-y-6 text-gray-700 leading-relaxed">
        <h1 class="text-3xl font-bold text-ink border-b border-gray-100 pb-4">
            আমাদের সম্পর্কে — <span class="text-brand-500">{{ \App\Support\SiteSettingsHelper::siteName() }}</span>
        </h1>
        <p class="text-lg text-gray-600 font-medium">
            স্বাগতম <strong class="text-ink">{{ \App\Support\SiteSettingsHelper::siteNameEn() }}</strong>-এ! আমরা দেশের প্রতিটি প্রান্তে ১০০% খাঁটি, ভেজালমুক্ত এবং প্রাকৃতিক খাদ্যপণ্য পৌঁছে দেওয়ার প্রত্যয় নিয়ে কাজ করছি।
        </p>
        <div class="grid sm:grid-cols-3 gap-4 my-8">
            <div class="bg-brand-50 border border-brand-100 p-4 rounded-xl text-center">
                <div class="text-2xl mb-1">🌿</div>
                <h3 class="font-bold text-ink mb-1">১০০% প্রাকৃতিক</h3>
                <p class="text-xs text-gray-600">কোনো প্রকার ক্ষতিকর কেমিক্যাল বা প্রিজারভেটিভ ছাড়া খাঁটি পণ্য।</p>
            </div>
            <div class="bg-brand-50 border border-brand-100 p-4 rounded-xl text-center">
                <div class="text-2xl mb-1">🚚</div>
                <h3 class="font-bold text-ink mb-1">দ্রুত হোম ডেলিভারি</h3>
                <p class="text-xs text-gray-600">সারাদেশে বিশ্বস্ত কুরিয়ার পার্টনারের মাধ্যমে ক্যাশ অন ডেলিভারি।</p>
            </div>
            <div class="bg-brand-50 border border-brand-100 p-4 rounded-xl text-center">
                <div class="text-2xl mb-1">🤝</div>
                <h3 class="font-bold text-ink mb-1">নির্ভরযোগ্য সেবা</h3>
                <p class="text-xs text-gray-600">পণ্য দেখে নেওয়ার সুযোগ ও দ্রুত গ্রাহক সেবা প্রদান।</p>
            </div>
        </div>
        <section class="space-y-3">
            <h2 class="text-xl font-bold text-ink">আমাদের লক্ষ্য ও উদ্দেশ্য</h2>
            <p>আমাদের প্রধান লক্ষ্য হলো ভেজালের ভিড়ে পরিবারগুলোর কাছে পুষ্টিকর, অর্গানিক ও নিরাপদ খাবারের জোগান নিশ্চিত করা — সরাসরি উৎস থেকে সংগ্রহ করে কঠোর মান নিয়ন্ত্রণের মাধ্যমে গ্রাহকদের কাছে সরবরাহ করা হয়।</p>
        </section>
        <section class="space-y-3">
            <h2 class="text-xl font-bold text-ink">যোগাযোগের ঠিকানা</h2>
            <ul class="list-disc list-inside text-sm space-y-1 text-gray-600 pl-2">
                <li><strong>হেল্পলাইন:</strong> <a href="tel:{{ \App\Support\SiteSettingsHelper::phoneRaw() }}" class="text-brand-500">{{ \App\Support\SiteSettingsHelper::phone() }}</a></li>
                <li><strong>ইমেইল:</strong> <a href="mailto:{{ \App\Support\SiteSettingsHelper::email() }}" class="text-brand-500">{{ \App\Support\SiteSettingsHelper::email() }}</a></li>
                <li><strong>ঠিকানা:</strong> {{ \App\Support\SiteSettingsHelper::address() }}</li>
            </ul>
        </section>
    </div>
</div>
@endsection
