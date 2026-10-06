@extends('landing.layout')

@section('content')
    {{-- Template 2 — Dark/premium look. --}}
    <section class="bg-secondary text-white px-4 pt-12 pb-10 text-center">
        @if ($landingPage->hero_image)
            <img src="{{ $landingPage->hero_image }}" alt="{{ $landingPage->headline }}"
                 class="mx-auto mb-6 w-full max-w-sm rounded-2xl shadow-2xl ring-1 ring-white/10 object-cover">
        @endif

        <h1 class="text-2xl md:text-4xl font-extrabold leading-snug">
            {{ $landingPage->headline }}
        </h1>

        @if ($landingPage->sub_headline)
            <p class="mt-3 text-base md:text-lg text-gray-300 max-w-xl mx-auto">
                {{ $landingPage->sub_headline }}
            </p>
        @endif

        @if ($landingPage->effectivePrice() > 0)
            <div class="mt-5 text-3xl font-extrabold text-accent">
                ৳{{ number_format($landingPage->effectivePrice()) }}
            </div>
        @endif

        <a href="#order-form"
           class="inline-block mt-6 bg-accent hover:bg-accent text-ink font-bold rounded-lg px-8 py-3 text-lg shadow-lg transition">
            {{ $landingPage->button_text }}
        </a>
    </section>

    @if ($landingPage->description)
        <section class="px-4 py-8 max-w-2xl mx-auto text-center bg-secondary">
            <p class="text-gray-300 whitespace-pre-line leading-relaxed">{{ $landingPage->description }}</p>
        </section>
    @endif

    @if (! empty($landingPage->gallery))
        <section class="px-4 py-8 max-w-3xl mx-auto bg-secondary">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                @foreach ($landingPage->gallery as $image)
                    <img src="{{ $image }}" alt="{{ $landingPage->title }}"
                         class="w-full aspect-square object-cover rounded-xl ring-1 ring-white/10">
                @endforeach
            </div>
        </section>
    @endif

    @if (! empty($landingPage->features))
        <section class="bg-secondary px-4 py-10">
            <div class="max-w-2xl mx-auto grid gap-4">
                @foreach ($landingPage->features as $feature)
                    <div class="flex gap-3 bg-secondary rounded-xl p-4 ring-1 ring-white/10">
                        <span class="flex-shrink-0 w-8 h-8 rounded-full bg-accent text-ink flex items-center justify-center font-bold">✓</span>
                        <div>
                            <div class="font-semibold text-white">{{ $feature['title'] ?? '' }}</div>
                            @if (! empty($feature['description']))
                                <div class="text-sm text-gray-400 mt-1">{{ $feature['description'] }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="px-4 py-10 bg-secondary">
        @if ($landingPage->product)
            @livewire('landing.landing-order-form', ['landingPage' => $landingPage])
        @else
            <p class="text-center text-gray-500">এই পণ্যটি এই মুহূর্তে পাওয়া যাচ্ছে না।</p>
        @endif
    </section>
@endsection
