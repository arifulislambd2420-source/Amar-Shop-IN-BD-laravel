@extends('layouts.app')

@section('title', 'অর্ডার ট্র্যাকিং — '.\App\Support\SiteSettingsHelper::siteName())

@section('content')
    @livewire('track.track-form')
@endsection
