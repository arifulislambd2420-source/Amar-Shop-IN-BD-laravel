@extends('layouts.app')

@section('content')
@php
    $settings = \App\Support\SiteSettingsHelper::class;
    $grid = 'grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 md:grid-cols-4';
@endphp
<div class="max-w-7xl mx-auto px-4">
    {{-- The sections, in the order (and only those) switched on in Site Setting → হোমপেজের সেকশন. --}}
    @foreach($sections as $section)
        @include('home.sections.'.$section)
    @endforeach

    @if($sections === [])
        <div class="py-16 text-center text-gray-500">
            <a href="{{ route('shop') }}" class="inline-flex h-12 items-center rounded-xl bg-brand-500 px-6 font-semibold text-white hover:bg-brand-600">সব পণ্য দেখুন</a>
        </div>
    @endif
</div>
@endsection
