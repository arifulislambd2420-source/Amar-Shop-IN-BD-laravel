@extends('layouts.app')

@section('title', 'অর্ডার ট্র্যাকিং — '.config('site.name'))

@section('content')
    @livewire('track.track-form')
@endsection
