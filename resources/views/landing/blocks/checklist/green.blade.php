{{-- Checklist — green: photo on the left, bordered card on the right. --}}
@php
    $items = array_values(array_filter(array_map(fn ($b) => is_array($b) ? ($b['text'] ?? null) : $b, (array) ($d['items'] ?? []))));
    $button = $d['button_text'] ?? '';
@endphp
<section class="bg-white px-4 py-8 md:py-12">
    <div class="max-w-6xl mx-auto grid gap-5 md:grid-cols-2 md:items-stretch">
        @if (! empty($d['image']))
            <img src="{{ $d['image'] }}" alt="{{ $d['heading'] ?? $landingPage->title }}" loading="lazy"
                 class="w-full h-full min-h-64 rounded-xl object-cover aspect-[4/3] md:aspect-auto">
        @endif

        <div class="rounded-xl border border-brand-200 bg-secondary/10 p-5 md:p-6 @if (empty($d['image'])) md:col-span-2 @endif">
            @if (! empty($d['heading']))
                <h2 class="text-xl md:text-2xl font-extrabold text-brand-600 leading-snug">{{ $d['heading'] }}</h2>
            @endif

            @if ($items)
                <ul class="mt-4 space-y-3 text-sm md:text-base text-ink">
                    @foreach ($items as $line)
                        <li class="flex gap-3">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-600 text-[11px] text-white" aria-hidden="true">✓</span>
                            <span>{{ $line }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if (! empty($d['intro']) || $button)
                <div class="mt-5 rounded-lg border border-secondary bg-white p-3 text-center">
                    @if (! empty($d['intro']))
                        <p class="text-sm md:text-base text-ink leading-relaxed">{{ $d['intro'] }}</p>
                    @endif
                    @if ($button)
                        <a href="#order-form" class="mt-3 flex items-center justify-center gap-2 rounded-md bg-brand-600 hover:bg-brand-500 text-white font-bold py-2.5 transition">
                            <span aria-hidden="true">🛒</span> {{ $button }}
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
