@php
    $images = array_values(array_filter((array) ($d['images'] ?? [])));
    $button = $d['button_text'] ?? '';
@endphp
@if ($images)
    <section class="bg-brand-600 px-4 py-8 md:py-10">
        <div class="max-w-5xl mx-auto">
            @if (! empty($d['heading']))
                <h2 class="text-center text-lg md:text-3xl font-extrabold text-white mb-5">{{ $d['heading'] }}</h2>
            @endif

            <div class="flex gap-3 overflow-x-auto snap-x snap-mandatory pb-3 -mx-4 px-4">
                @foreach ($images as $image)
                    <img src="{{ $image }}" alt="কাস্টমার রিভিউ" loading="lazy"
                         class="snap-center shrink-0 w-[80%] sm:w-[45%] md:w-[32%] rounded-xl bg-white object-contain">
                @endforeach
            </div>

            @if ($button)
                <div class="mt-4 text-center">
                    <a href="#order-form" class="inline-flex items-center gap-2 rounded-lg bg-secondary text-brand-600 font-bold px-6 py-2.5 shadow hover:brightness-95 transition">
                        <span aria-hidden="true">🛒</span> {{ $button }}
                    </a>
                </div>
            @endif
        </div>
    </section>
@endif
