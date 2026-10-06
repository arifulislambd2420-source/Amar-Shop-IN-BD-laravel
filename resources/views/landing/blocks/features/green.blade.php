{{-- Features — green: dark band (continues the hero wave) with white cards. --}}
@php
    $items = array_values(array_filter((array) ($d['items'] ?? []), fn ($i) => filled($i['title'] ?? null)));
@endphp
@if ($items)
    <section class="bg-brand-600 px-4 pb-10 pt-2 md:pb-14">
        <div class="max-w-6xl mx-auto">
            @if (! empty($d['heading']))
                <h2 class="mb-5 text-center text-xl md:text-3xl font-extrabold text-white">{{ $d['heading'] }}</h2>
            @endif
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-5">
                @foreach ($items as $item)
                    <div class="rounded-xl bg-white p-4 text-center shadow-lg">
                        @if (! empty($item['icon']))
                            <img src="{{ $item['icon'] }}" alt="" loading="lazy" class="mx-auto mb-3 h-14 w-14 md:h-16 md:w-16 object-contain">
                        @else
                            <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-brand-600 text-white text-xl font-bold" aria-hidden="true">{{ $loop->iteration }}</span>
                        @endif
                        <h3 class="font-bold text-sm md:text-base text-brand-600 leading-snug">{{ $item['title'] }}</h3>
                        @if (! empty($item['description']))
                            <p class="mt-1 text-xs md:text-sm text-gray-600 leading-relaxed">{{ $item['description'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
