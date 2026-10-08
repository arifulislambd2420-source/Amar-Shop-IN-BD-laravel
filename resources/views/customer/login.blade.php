@extends('layouts.app')

@section('title', 'লগইন — '.\App\Support\SiteSettingsHelper::siteName())
@section('robots', 'noindex, nofollow')

@section('content')
<x-auth-card title="আবার স্বাগতম!" subtitle="মোবাইল নম্বর আর পাসওয়ার্ড দিয়ে লগইন করুন">
    <form action="{{ route('customer.login.submit') }}" method="POST" class="flex flex-col gap-5" x-data="{ busy: false }" @submit="busy = true" novalidate>
        @csrf
        <x-form-input name="phone" label="মোবাইল নম্বর" type="tel" icon="phone" placeholder="01XXXXXXXXX"
            inputmode="numeric" autocomplete="tel" maxlength="14" required />
        <x-form-input name="password" label="পাসওয়ার্ড" type="password" icon="lock" placeholder="আপনার পাসওয়ার্ড"
            autocomplete="current-password" required />

        <label class="flex min-h-11 cursor-pointer select-none items-center gap-3 text-sm text-gray-700">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))
                class="h-5 w-5 rounded-md border-gray-300 accent-brand-500">
            আমাকে মনে রাখুন
        </label>

        <button type="submit" :disabled="busy"
            class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-500 text-base font-semibold text-white shadow-sm transition hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:cursor-wait disabled:opacity-80">
            <svg x-show="busy" x-cloak class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
            লগইন করুন
        </button>
    </form>

    <x-slot:footer>
        অ্যাকাউন্ট নেই? <a href="{{ route('customer.register') }}" class="font-semibold text-brand-600 hover:underline">নতুন অ্যাকাউন্ট খুলুন</a>
    </x-slot:footer>
</x-auth-card>
@endsection
