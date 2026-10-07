@php
    $items = array_values(array_filter((array) ($d['items'] ?? []), fn ($i) => filled($i['title'] ?? null)));
@endphp
@if ($items)
    <section class="px-4 py-8 md:py-12 bg-white">
        <div class="max-w-5xl mx-auto">
            @if (! empty($d['heading']))
                <h2 class="text-center text-xl md:text-3xl font-extrabold text-ink mb-6">{{ $d['heading'] }}</h2>
            @endif
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-5">
                @foreach ($items as $item)
                    <div class="rounded-2xl border border-secondary bg-secondary/15 p-4 text-center shadow-sm">
                        @if (! empty($item['icon']))
                            <img width="800" height="800" src="{{ $item['icon'] }}" alt="" loading="lazy" class="mx-auto mb-3 h-14 w-14 md:h-16 md:w-16 rounded-full object-cover">
                        @else
                            <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-brand-500 text-white text-xl font-bold" aria-hidden="true">{{ $loop->iteration }}</span>
                        @endif
                        <h3 class="font-bold text-sm md:text-base text-ink leading-snug">{{ $item['title'] }}</h3>
                        @if (! empty($item['description']))
                            <p class="mt-1 text-xs md:text-sm text-gray-600 leading-relaxed">{{ $item['description'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
