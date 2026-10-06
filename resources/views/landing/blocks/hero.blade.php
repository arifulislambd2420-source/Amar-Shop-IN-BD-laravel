{{-- Hero — default look (used by the purple template, after the bra page):
     dark band with the headline, a bordered price banner and the order
     button; then a panel with the image/video on the left and a lavender
     box on the right (heading, highlighted pill, product line, feature
     list, button). green and cream have their own hero/{tpl}.blade.php. --}}
@php
    $images = array_values(array_filter((array) ($d['images'] ?? [])));
    if (! $images && ! empty($d['image'])) {
        $images = [$d['image']];
    }
    $video = \App\Support\VideoEmbed::parse($d['video_url'] ?? null);
    $button = $d['button_text'] ?? 'অর্ডার করুন';
    $bullets = array_values(array_filter(array_map(fn ($b) => is_array($b) ? ($b['text'] ?? null) : $b, (array) ($d['bullets'] ?? []))));
@endphp

<section class="relative bg-brand-600 text-white text-center px-4 pt-7 pb-9">
    <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold leading-snug max-w-3xl mx-auto">{{ $d['headline'] ?? $landingPage->headline }}</h1>

    @if (! empty($d['offer_line']))
        <div class="mt-4 inline-block max-w-full rounded-md border-2 border-secondary bg-white px-4 py-2 text-base sm:text-lg md:text-xl font-bold text-brand-600 shadow">
            {{ $d['offer_line'] }}
        </div>
    @endif

    @if ($button)
        <div class="mt-4">
            <a href="#order-form" class="inline-flex items-center gap-2 rounded-md bg-secondary px-6 py-2.5 font-bold text-brand-600 shadow hover:brightness-95 transition">
                <span aria-hidden="true">🛒</span> {{ $button }}
            </a>
        </div>
    @endif

    {{-- scalloped lower edge --}}
    <div class="pointer-events-none absolute inset-x-0 -bottom-px h-3"
         style="background: radial-gradient(circle at 8px 0, transparent 7px, var(--secondary-mix, #ffffff) 8px) 0 0 / 16px 12px repeat-x;"></div>
</section>

<section class="bg-white px-4 py-6 md:py-10">
    <div class="max-w-5xl mx-auto grid gap-5 md:grid-cols-2 md:items-start">
        @include('landing.blocks._hero-media', ['images' => $images, 'video' => $video])

        <div class="rounded-xl border-2 border-dashed border-secondary bg-secondary/20 p-4 @if (! $images) md:col-span-2 max-w-2xl mx-auto w-full @endif">
            @if (! empty($d['badge_text']))
                <h2 class="text-lg md:text-2xl font-extrabold leading-snug text-brand-600 text-center">{{ $d['badge_text'] }}</h2>
            @endif

            @if (! empty($d['highlight']))
                <p class="mt-3 rounded-md border border-secondary bg-white/80 px-3 py-2 text-center text-sm md:text-base font-semibold text-brand-600">{{ $d['highlight'] }}</p>
            @endif

            @if (! empty($d['description']))
                <p class="mt-3 text-center text-sm md:text-base text-ink leading-relaxed">☺ {{ $d['description'] }}</p>
            @endif

            @if ($bullets)
                <div class="mt-3 rounded-lg border border-secondary bg-white p-3">
                    @if (! empty($d['list_heading']))
                        <h3 class="mb-2 text-base md:text-lg font-bold text-brand-600">{{ $d['list_heading'] }}</h3>
                    @endif
                    <ul class="space-y-2 text-sm md:text-base text-ink">
                        @foreach ($bullets as $line)
                            <li class="flex gap-2"><span class="mt-0.5 text-brand-500" aria-hidden="true">◉</span><span>{{ $line }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($button)
                <a href="#order-form" class="mt-3 flex items-center justify-center gap-2 rounded-md bg-secondary py-2.5 font-bold text-brand-600 hover:brightness-95 transition">
                    <span aria-hidden="true">🛒</span> {{ $button }}
                </a>
            @endif
        </div>
    </div>
</section>
