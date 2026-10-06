@php
    $items = array_values(array_filter(array_map(fn ($b) => is_array($b) ? ($b['text'] ?? null) : $b, (array) ($d['items'] ?? []))));
    $button = $d['button_text'] ?? '';
@endphp
<section class="bg-secondary/20 px-4 py-8 md:py-12">
    <div class="max-w-5xl mx-auto">
        @if (! empty($d['heading']))
            <h2 class="mx-auto mb-6 max-w-3xl rounded-lg bg-brand-600 px-4 py-3 text-center text-lg md:text-2xl font-extrabold text-white shadow">{{ $d['heading'] }}</h2>
        @endif

        <div class="grid gap-5 md:grid-cols-2 md:items-center">
            <div class="rounded-xl border-2 border-dashed border-secondary bg-white p-4">
                @if (! empty($d['intro']))
                    <p class="mb-3 rounded-lg border border-secondary bg-secondary/15 p-3 text-sm md:text-base text-ink leading-relaxed">{{ $d['intro'] }}</p>
                @endif
                @if ($items)
                    <ul class="space-y-2 text-sm md:text-base text-ink">
                        @foreach ($items as $line)
                            <li class="flex gap-2"><span class="mt-0.5 text-brand-500" aria-hidden="true">✔</span><span>{{ $line }}</span></li>
                        @endforeach
                    </ul>
                @endif
                @if ($button)
                    <a href="#order-form" class="mt-4 flex items-center justify-center gap-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold py-2.5 transition">
                        <span aria-hidden="true">🛒</span> {{ $button }}
                    </a>
                @endif
            </div>

            @if (! empty($d['image']))
                <img src="{{ $d['image'] }}" alt="{{ $d['heading'] ?? $landingPage->title }}" loading="lazy"
                     class="w-full rounded-xl border-2 border-dashed border-secondary object-cover aspect-[4/5] md:order-last">
            @endif
        </div>
    </div>
</section>
