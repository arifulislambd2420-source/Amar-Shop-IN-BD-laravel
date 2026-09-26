@extends('layouts.app')

@section('title', 'চেকআউট — '.config('site.name'))

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">বিলিং তথ্য</h1>
    @livewire('checkout.checkout-form')
</div>
@endsection
