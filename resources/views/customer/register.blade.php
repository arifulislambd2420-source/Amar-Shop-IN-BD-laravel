@extends('layouts.app')

@section('title', 'রেজিস্টার — '.\App\Support\SiteSettingsHelper::siteName())
@section('robots', 'noindex, nofollow')

@section('content')
<x-auth-card title="নতুন অ্যাকাউন্ট খুলুন" subtitle="অর্ডার ট্র্যাক করা আর ঠিকানা সেভ রাখা আরও সহজ হবে">
    <form action="{{ route('customer.register.submit') }}" method="POST" class="flex flex-col gap-5" x-data="{ busy: false }" @submit="busy = true" novalidate>
        @csrf
        <x-form-input name="name" label="পূর্ণ নাম" icon="user" placeholder="আপনার নাম"
            autocomplete="name" maxlength="255" required />
        <x-form-input name="phone" label="মোবাইল নম্বর" type="tel" icon="phone" placeholder="01XXXXXXXXX"
            inputmode="numeric" autocomplete="tel" maxlength="14" required />
        <x-form-input name="password" label="পাসওয়ার্ড" type="password" icon="lock" placeholder="কমপক্ষে ৬ অক্ষর"
            hint="কমপক্ষে ৬ অক্ষরের পাসওয়ার্ড দিন।" autocomplete="new-password" required />

        <button type="submit" :disabled="busy"
            class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-500 text-base font-semibold text-white shadow-sm transition hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:cursor-wait disabled:opacity-80">
            <svg x-show="busy" x-cloak class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
            অ্যাকাউন্ট খুলুন
        </button>
    </form>

    <x-slot:footer>
        আগে থেকেই অ্যাকাউন্ট আছে? <a href="{{ route('customer.login') }}" class="font-semibold text-brand-600 hover:underline">লগইন করুন</a>
    </x-slot:footer>
</x-auth-card>
@endsection
