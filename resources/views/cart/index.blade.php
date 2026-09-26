@extends('layouts.app')

@section('title', 'কার্ট — '.config('site.name'))

@section('content')
    @livewire('cart.cart-page')
@endsection
