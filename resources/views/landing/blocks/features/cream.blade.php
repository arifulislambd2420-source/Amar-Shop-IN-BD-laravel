{{-- Features — cream: photo collage on the left; label, two-tone heading,
     2-column feature list and buttons on the right. --}}
@php
    $items = array_values(array_filter((array) ($d['items'] ?? []), fn ($i) => filled($i['title'] ?? null)));
    $images = array_slice(array_values(array_filter((array) ($d['images'] ?? []))), 0, 4);
    $button = $d['button_text'] ?? '';
    $phone = trim((string) ($d['phone'] ?? ''));
    $tel = $phone !== '' ? preg_replace('/[^0-9+]/', '', $phone) : '';
@endphp
<section class="bg-white px-4 py-10 md:py-16">
    <div class="max-w-6xl mx-auto grid gap-8 md:grid-cols-2 md:items-center">
        @if ($images)
            <div class="grid grid-cols-2 gap-3">
                @foreach ($images as $image)
                    <img width="800" height="800" src="{{ $image }}" alt="{{ $landingPage->title }}" loading="lazy"
                         class="w-full rounded-xl object-cover shadow-md {{ $loop->odd ? 'aspect-[3/4]' : 'aspect-[3/4] mt-6' }}">
                @endforeach
            </div>
        @endif

        <div class="@if (! $images) md:col-span-2 max-w-3xl mx-auto w-full @endif">
            @if (! empty($d['label']))
                <span class="inline-block rounded-full bg-secondary/15 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-secondary">{{ $d['label'] }}</span>
            @endif
            @if (! empty($d['heading']))
                <h2 class="mt-2 text-3xl md:text-4xl font-extrabold leading-tight text-ink">
                    {{ $d['heading'] }}
                    @if (! empty($d['accent']))
                        <span class="block text-secondary">{{ $d['accent'] }}</span>
                    @endif
                </h2>
            @endif
            @if (! empty($d['description']))
                <p class="mt-3 text-sm md:text-base leading-relaxed text-gray-500">{{ $d['description'] }}</p>
            @endif

            @if ($items)
                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($items as $item)
                        <div class="flex gap-3">
                            @if (! empty($item['icon']))
                                <img width="800" height="800" src="{{ $item['icon'] }}" alt="" loading="lazy" class="h-10 w-10 shrink-0 rounded-lg object-cover">
                            @else
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-secondary/15 text-secondary" aria-hidden="true">✦</span>
                            @endif
                            <div>
                                <h3 class="text-sm font-bold text-ink leading-snug">{{ $item['title'] }}</h3>
                                @if (! empty($item['description']))
                                    <p class="mt-0.5 text-xs text-gray-500 leading-relaxed">{{ $item['description'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($button || $phone !== '')
                <div class="mt-6 flex flex-wrap items-center gap-3">
                    @if ($button)
                        <a href="#order-form" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-6 py-3 font-bold text-white shadow hover:bg-brand-500 transition">
                            <span aria-hidden="true">🛒</span> {{ $button }}
                        </a>
                    @endif
                    @if ($phone !== '')
                        <a href="tel:{{ $tel }}" class="rounded-lg border border-gray-300 px-5 py-3 font-semibold text-ink hover:bg-gray-50 transition">{{ $phone }}</a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
