{{-- Hero — cream: big dark headline with an accent second line, text and a
     dark button on the left, a portrait video/photo card on the right. --}}
@php
    $images = array_values(array_filter((array) ($d['images'] ?? [])));
    if (! $images && ! empty($d['image'])) {
        $images = [$d['image']];
    }
    $video = \App\Support\VideoEmbed::parse($d['video_url'] ?? null);
    $button = $d['button_text'] ?? 'অর্ডার করুন';
    $bullets = array_values(array_filter(array_map(fn ($b) => is_array($b) ? ($b['text'] ?? null) : $b, (array) ($d['bullets'] ?? []))));
@endphp

<section class="px-4 pb-10 pt-8 md:pb-16 md:pt-14">
    <div class="max-w-6xl mx-auto grid gap-8 md:grid-cols-[1.25fr_1fr] md:items-center">
        <div>
            <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold leading-[1.15] text-ink">{{ $d['headline'] ?? $landingPage->headline }}</h1>

            @if (! empty($d['offer_line']))
                <p class="mt-2 text-xl md:text-3xl font-bold text-secondary">{{ $d['offer_line'] }}</p>
            @endif

            @if (! empty($d['description']))
                <p class="mt-4 text-sm md:text-base leading-relaxed text-gray-500 whitespace-pre-line">{{ $d['description'] }}</p>
            @endif

            @if (! empty($d['badge_text']))
                <p class="mt-3 text-sm font-semibold text-ink">{{ $d['badge_text'] }}</p>
            @endif

            @if ($bullets)
                <ul class="mt-4 space-y-2 text-sm md:text-base text-gray-600">
                    @foreach ($bullets as $line)
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-secondary/20 text-[10px] text-secondary" aria-hidden="true">✦</span>
                            <span>{{ $line }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($button)
                <a href="#order-form" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-7 py-3 font-bold text-white shadow-lg hover:bg-brand-500 transition">
                    <span aria-hidden="true">🛒</span> {{ $button }}
                </a>
            @endif
        </div>

        @if ($images)
            <div class="mx-auto w-full max-w-sm md:max-w-none">
                @include('landing.blocks._hero-media', ['images' => $images, 'video' => $video, 'aspect' => 'aspect-[3/4]', 'wrapClass' => 'border-0! rounded-3xl! shadow-2xl'])
            </div>
        @endif
    </div>
</section>
