@php
    $images = array_values(array_filter((array) ($d['images'] ?? [])));
    $grid = ($d['layout'] ?? 'carousel') === 'grid';
@endphp
@if ($images)
    <section class="bg-white px-4 py-8 md:py-10">
        <div class="max-w-5xl mx-auto">
            @if (! empty($d['heading']))
                <h2 class="text-center text-xl md:text-3xl font-extrabold text-ink mb-5">{{ $d['heading'] }}</h2>
            @endif

            @if ($grid)
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach ($images as $image)
                        <img src="{{ $image }}" alt="{{ $landingPage->title }}" loading="lazy"
                             class="w-full aspect-square object-cover rounded-xl border-2 border-dashed border-brand-200">
                    @endforeach
                </div>
            @else
                {{-- Scroll-snap carousel: swipeable on mobile, no JS needed. --}}
                <div class="flex gap-3 overflow-x-auto snap-x snap-mandatory pb-3 -mx-4 px-4">
                    @foreach ($images as $image)
                        <img src="{{ $image }}" alt="{{ $landingPage->title }}" loading="lazy"
                             class="snap-center shrink-0 w-[72%] sm:w-[40%] md:w-[31%] aspect-square object-cover rounded-xl border-2 border-dashed border-brand-200">
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif
