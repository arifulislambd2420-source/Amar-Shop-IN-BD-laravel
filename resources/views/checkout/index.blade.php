@extends('layouts.app')

@section('title', 'চেকআউট — '.\App\Support\SiteSettingsHelper::siteName())
@section('robots', 'noindex, nofollow')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-5 md:py-8">
    <h1 class="mb-4 text-xl font-bold text-gray-900 md:mb-6 md:text-2xl">চেকআউট</h1>
    @if (session('status'))
        <div class="mb-5 rounded-2xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-500" role="alert">
            {{ session('status') }}
        </div>
    @endif
    @livewire('checkout.checkout-form')
</div>
@endsection
