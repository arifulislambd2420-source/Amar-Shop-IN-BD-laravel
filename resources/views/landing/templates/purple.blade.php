@extends('landing.layout')

@section('content')
    {{-- Purple: dark banded hero, pink panels, review carousel (bra-page style). --}}
    @include('landing.templates._page', ['tpl' => 'purple'])
@endsection
