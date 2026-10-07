@extends('layouts.app')

@section('title', 'রিটার্ন ও রিফান্ড পলিসি — '.\App\Support\SiteSettingsHelper::siteName())

@section('content')
<div class="max-w-4xl mx-auto px-4 py-10">
    <nav class="text-xs text-gray-500 mb-6">
        <a href="{{ url('/') }}" class="hover:text-brand-500">হোম</a> &gt; <span>রিটার্ন ও রিফান্ড পলিসি</span>
    </nav>
    <div class="bg-white rounded-2xl border border-gray-200 p-6 md:p-10 shadow-sm space-y-6 text-gray-700 leading-relaxed">
        <h1 class="text-3xl font-bold text-ink border-b border-gray-100 pb-4">রিটার্ন ও রিফান্ড পলিসি</h1>
        @if(filled($customContent ?? null))
            <div class="rich-text text-gray-700">{!! \App\Support\Html::render($customContent) !!}</div>
        @else
        <section class="space-y-3">
            <h2 class="text-xl font-bold text-ink">১. রিটার্নের শর্ত</h2>
            <p class="text-sm">পণ্য হাতে পাওয়ার সাথে সাথে যাচাই করুন। ভুল পণ্য বা ক্ষতিগ্রস্ত অবস্থায় পৌঁছালে ডেলিভারির ২৪ ঘণ্টার মধ্যে আমাদের হেল্পলাইনে জানান।</p>
        </section>
        <section class="space-y-3">
            <h2 class="text-xl font-bold text-ink">২. রিফান্ড প্রক্রিয়া</h2>
            <p class="text-sm">রিটার্ন অনুমোদিত হলে ৭–১০ কার্যদিবসের মধ্যে রিফান্ড প্রক্রিয়া সম্পন্ন হয় (ক্যাশ অন ডেলিভারি অর্ডারের ক্ষেত্রে বিকাশ/নগদের মাধ্যমে)।</p>
        </section>
        <section class="space-y-3">
            <h2 class="text-xl font-bold text-ink">৩. যেসব ক্ষেত্রে রিটার্ন প্রযোজ্য নয়</h2>
            <ul class="list-disc list-inside text-sm space-y-1 pl-2">
                <li>প্যাকেট খোলা হয়েছে এবং পণ্য ব্যবহার করা হয়েছে এমন অবস্থায়।</li>
                <li>খাদ্যপণ্যের মেয়াদ সংক্রান্ত কোনো সমস্যা না থাকলে।</li>
            </ul>
        </section>
        @endif
    </div>
</div>
@endsection
