{{-- Checklist — default look (purple, after the bra page): a pink strip with a
     dark ribbon heading, then a bordered pink card (intro, list, button) next
     to a photo. green has its own checklist/green.blade.php. --}}
@php
    $items = array_values(array_filter(array_map(fn ($b) => is_array($b) ? ($b['text'] ?? null) : $b, (array) ($d['items'] ?? []))));
    $button = $d['button_text'] ?? '';
@endphp
<section>
    @if (! empty($d['heading']))
        <div class="bg-secondary px-4 py-5 text-center">
            <h2 class="mx-auto max-w-3xl rounded-md bg-brand-600 px-4 py-2.5 text-lg md:text-2xl font-extrabold text-white shadow">{{ $d['heading'] }}</h2>
        </div>
    @endif

    <div class="bg-white px-4 py-7 md:py-10">
        <div class="max-w-5xl mx-auto grid gap-5 md:grid-cols-2 md:items-start">
            <div class="rounded-xl border-2 border-dashed border-secondary bg-secondary/20 p-4">
                @if (! empty($d['intro']))
                    <p class="mb-3 rounded-md border border-secondary bg-white/80 p-3 text-sm md:text-base text-brand-600 leading-relaxed">{{ $d['intro'] }}</p>
                @endif
                @if ($items)
                    <ul class="space-y-2 text-sm md:text-base text-ink">
                        @foreach ($items as $line)
                            <li class="flex gap-2"><span class="mt-0.5 text-brand-500" aria-hidden="true">✔</span><span>{{ $line }}</span></li>
                        @endforeach
                    </ul>
                @endif
                @if ($button)
                    <a href="#order-form" class="mt-4 flex items-center justify-center gap-2 rounded-md bg-secondary py-2.5 font-bold text-brand-600 hover:brightness-95 transition">
                        <span aria-hidden="true">🛒</span> {{ $button }}
                    </a>
                @endif
            </div>

            @if (! empty($d['image']))
                <img width="800" height="800" src="{{ $d['image'] }}" alt="{{ $d['heading'] ?? $landingPage->title }}" loading="lazy"
                     class="w-full rounded-xl border-2 border-dashed border-secondary object-cover aspect-[4/5] md:order-last">
            @endif
        </div>
    </div>
</section>
