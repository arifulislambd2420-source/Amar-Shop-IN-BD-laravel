@extends('landing.layout')

@section('content')
    {{-- Template 3 — Minimal/clean: lots of whitespace, single accent color. --}}
    <section class="px-4 pt-14 pb-10 text-center max-w-xl mx-auto">
        <h1 class="text-2xl md:text-3xl font-bold text-ink leading-snug tracking-tight">
            {{ $landingPage->headline }}
        </h1>

        @if ($landingPage->sub_headline)
            <p class="mt-3 text-gray-500">
                {{ $landingPage->sub_headline }}
            </p>
        @endif

        @if ($landingPage->hero_image)
            <img src="{{ $landingPage->hero_image }}" alt="{{ $landingPage->headline }}" width="800" height="800" fetchpriority="high"
                 class="mx-auto mt-8 w-full rounded-xl object-cover">
        @endif

        @if ($landingPage->effectivePrice() > 0)
            <div class="mt-6 text-2xl font-bold text-ink">
                ৳{{ number_format($landingPage->effectivePrice()) }}
            </div>
        @endif

        <a href="#order-form"
           class="inline-block mt-6 bg-secondary hover:bg-secondary/80 text-white font-semibold rounded-md px-8 py-3 transition">
            {{ $landingPage->button_text }}
        </a>
    </section>

    @if ($landingPage->description)
        <section class="px-4 py-6 max-w-xl mx-auto border-t border-gray-100">
            <p class="text-gray-600 whitespace-pre-line leading-relaxed text-center">{{ $landingPage->description }}</p>
        </section>
    @endif

    @if (! empty($landingPage->gallery))
        <section class="px-4 py-8 max-w-2xl mx-auto">
            <div class="grid grid-cols-3 gap-2">
                @foreach ($landingPage->gallery as $image)
                    <img src="{{ $image }}" alt="{{ $landingPage->title }}" width="600" height="600" loading="lazy"
                         class="w-full aspect-square object-cover rounded-lg">
                @endforeach
            </div>
        </section>
    @endif

    @if (! empty($landingPage->features))
        <section class="px-4 py-10 max-w-xl mx-auto border-t border-gray-100">
            <div class="grid gap-5">
                @foreach ($landingPage->features as $feature)
                    <div class="flex gap-3">
                        <span class="flex-shrink-0 w-1.5 h-1.5 mt-2 rounded-full bg-secondary"></span>
                        <div>
                            <div class="font-medium text-ink">{{ $feature['title'] ?? '' }}</div>
                            @if (! empty($feature['description']))
                                <div class="text-sm text-gray-500 mt-1">{{ $feature['description'] }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="px-4 py-10 border-t border-gray-100">
        @if ($landingPage->product)
            @livewire('landing.landing-order-form', ['landingPage' => $landingPage])
        @else
            <p class="text-center text-gray-500">এই পণ্যটি এই মুহূর্তে পাওয়া যাচ্ছে না।</p>
        @endif
    </section>
@endsection
