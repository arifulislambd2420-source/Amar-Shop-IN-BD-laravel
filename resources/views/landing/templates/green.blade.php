@extends('landing.layout')

@section('content')
    {{-- Green: wave hero → dark-green feature band → split sections (mango-style). --}}
    @include('landing.templates._page', ['tpl' => 'green'])
@endsection
