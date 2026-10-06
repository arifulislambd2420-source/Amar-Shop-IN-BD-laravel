@extends('landing.layout')

@section('content')
    {{-- Cream: warm light page, dark buttons, orange accents, colour grid (khimar-style). --}}
    @include('landing.templates._page', ['tpl' => 'cream'])
@endsection
