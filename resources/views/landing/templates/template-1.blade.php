@extends('landing.layout')

@section('content')
    {{-- Template 1 — Warm/classic: matches the main site's orange branding. --}}
    <section class="bg-gradient-to-b from-orange-50 to-white px-4 pt-10 pb-8 text-center">
        @if ($landingPage->hero_image)
            <img src="{{ $landingPage->hero_image }}" alt="{{ $landingPage->headline }}"
                 class="mx-auto mb-6 w-full max-w-sm rounded-2xl shadow-lg object-cover">
        @endif

        <h1 class="text-2xl md:text-4xl font-extrabold text-gray-900 leading-snug">
            {{ $landingPage->headline }}
        </h1>

        @if ($landingPage->sub_headline)
            <p class="mt-3 text-base md:text-lg text-gray-600 max-w-xl mx-auto">
                {{ $landingPage->sub_headline }}
            </p>
        @endif

        @if ($landingPage->effectivePrice() > 0)
            <div class="mt-5 text-3xl font-extrabold text-orange-600">
                ৳{{ number_format($landingPage->effectivePrice()) }}
            </div>
        @endif

        <a href="#order-form"
           class="inline-block mt-6 bg-orange-500 hover:bg-orange-600 text-white font-bold rounded-full px-8 py-3 text-lg shadow-md transition">
            {{ $landingPage->button_text }}
        </a>
    </section>

    @if ($landingPage->description)
        <section class="px-4 py-8 max-w-2xl mx-auto text-center">
            <p class="text-gray-700 whitespace-pre-line leading-relaxed">{{ $landingPage->description }}</p>
        </section>
    @endif

    @if (! empty($landingPage->gallery))
        <section class="px-4 py-6 max-w-3xl mx-auto">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                @foreach ($landingPage->gallery as $image)
                    <img src="{{ $image }}" alt="{{ $landingPage->title }}"
                         class="w-full aspect-square object-cover rounded-xl shadow-sm">
                @endforeach
            </div>
        </section>
    @endif

    @if (! empty($landingPage->features))
        <section class="bg-orange-50 px-4 py-10">
            <div class="max-w-2xl mx-auto grid gap-4">
                @foreach ($landingPage->features as $feature)
                    <div class="flex gap-3 bg-white rounded-xl p-4 shadow-sm">
                        <span class="flex-shrink-0 w-8 h-8 rounded-full bg-orange-500 text-white flex items-center justify-center font-bold">✓</span>
                        <div>
                            <div class="font-semibold text-gray-900">{{ $feature['title'] ?? '' }}</div>
                            @if (! empty($feature['description']))
                                <div class="text-sm text-gray-600 mt-1">{{ $feature['description'] }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="px-4 py-10 bg-white">
        @livewire('landing.landing-order-form', ['landingPage' => $landingPage])
    </section>
@endsection
