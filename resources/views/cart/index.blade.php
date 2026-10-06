@extends('layouts.app')

@section('title', 'কার্ট — '.\App\Support\SiteSettingsHelper::siteName())

@section('content')
    @livewire('cart.cart-page')
@endsection
