{{-- Gallery — green: accent heading, photos in a row (swipeable on mobile). --}}
@php
    $images = array_values(array_filter((array) ($d['images'] ?? [])));
@endphp
@if ($images)
    <section class="bg-white px-4 py-8 md:py-10">
        <div class="max-w-6xl mx-auto">
            @if (! empty($d['heading']))
                <h2 class="mb-5 text-center text-xl md:text-3xl font-extrabold text-brand-600">{{ $d['heading'] }}</h2>
            @endif

            <div class="flex gap-3 overflow-x-auto snap-x snap-mandatory pb-2 -mx-4 px-4 md:mx-0 md:px-0 md:grid md:grid-cols-4 md:overflow-visible">
                @foreach ($images as $image)
                    <img src="{{ $image }}" alt="{{ $landingPage->title }}" loading="lazy"
                         class="snap-center shrink-0 w-[62%] sm:w-[40%] md:w-auto aspect-[4/5] rounded-xl object-cover border border-secondary">
                @endforeach
            </div>
        </div>
    </section>
@endif
