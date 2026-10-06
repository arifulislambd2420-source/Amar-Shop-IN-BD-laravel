@extends('layouts.app')

@section('title', 'চেকআউট — '.\App\Support\SiteSettingsHelper::siteName())

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">বিলিং তথ্য</h1>
    @if (session('status'))
        <div class="mb-6 rounded-lg border border-error-200 bg-error-50 text-error-700 text-sm px-4 py-3">
            {{ session('status') }}
        </div>
    @endif
    @livewire('checkout.checkout-form')
</div>
@endsection
