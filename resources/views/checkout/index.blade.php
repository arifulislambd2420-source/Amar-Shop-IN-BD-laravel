@extends('layouts.app')

@section('title', 'চেকআউট — '.config('site.name'))

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">বিলিং তথ্য</h1>
    @if (session('status'))
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 text-red-700 text-sm px-4 py-3">
            {{ session('status') }}
        </div>
    @endif
    @livewire('checkout.checkout-form')
</div>
@endsection
