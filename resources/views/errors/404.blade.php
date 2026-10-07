@extends('layouts.app')

@section('title', 'পেজটি পাওয়া যায়নি — '.\App\Support\SiteSettingsHelper::siteName())
@section('robots', 'noindex, nofollow')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-20 text-center">
    <p class="text-7xl font-extrabold text-brand-500">৪০৪</p>
    <h1 class="mt-4 text-2xl font-bold">দুঃখিত, পেজটি পাওয়া যায়নি</h1>
    <p class="mt-2 text-gray-600">লিংকটি ভুল হতে পারে অথবা পেজটি সরিয়ে নেওয়া হয়েছে।</p>
    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        <a href="{{ route('home') }}" class="rounded-lg bg-brand-500 px-6 py-3 font-semibold text-white hover:bg-brand-600">হোমে যান</a>
        <a href="{{ route('shop') }}" class="rounded-lg border border-gray-300 bg-white px-6 py-3 font-semibold text-ink hover:border-brand-500 hover:text-brand-500">শপে যান</a>
    </div>
</div>
@endsection
