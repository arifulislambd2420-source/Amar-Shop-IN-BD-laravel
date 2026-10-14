{{-- Guarantee / trust — a row of small promise cards (guarantee, delivery,
     return, support, cash on delivery …). --}}
@php
    $items = array_values(array_filter((array) ($d['items'] ?? []), fn ($i) => is_array($i) && filled($i['title'] ?? null)));
@endphp
@if ($items)
    <section class="px-4 py-10 md:py-14 bg-secondary/10">
        <div class="max-w-6xl mx-auto">
            @if (! empty($d['heading']))
                <h2 class="mb-6 text-center text-2xl md:text-4xl font-extrabold leading-tight text-ink">{{ $d['heading'] }}</h2>
            @endif
            <div class="grid grid-cols-2 gap-3 md:gap-5 {{ count($items) >= 4 ? 'md:grid-cols-4' : 'md:grid-cols-3' }}">
                @foreach ($items as $item)
                    <div class="rounded-xl bg-white p-4 text-center shadow-sm ring-1 ring-gray-100">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-600/10 text-2xl" aria-hidden="true">{{ $item['icon'] ?? '🛡️' }}</span>
                        <p class="mt-2 text-sm md:text-base font-bold text-ink">{{ $item['title'] }}</p>
                        @if (! empty($item['description']))
                            <p class="mt-1 text-xs md:text-sm text-gray-500 leading-relaxed">{{ $item['description'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
