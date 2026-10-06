{{-- Hero — green: light page with headline + text on the left, big photo on
     the right, ending in a wave into the (dark) section below. --}}
@php
    $images = array_values(array_filter((array) ($d['images'] ?? [])));
    if (! $images && ! empty($d['image'])) {
        $images = [$d['image']];
    }
    $video = \App\Support\VideoEmbed::parse($d['video_url'] ?? null);
    $button = $d['button_text'] ?? 'অর্ডার করতে চাই';
    $bullets = array_values(array_filter(array_map(fn ($b) => is_array($b) ? ($b['text'] ?? null) : $b, (array) ($d['bullets'] ?? []))));
@endphp

<section class="bg-secondary/15 pt-7 md:pt-12">
    <div class="max-w-6xl mx-auto px-4 grid gap-6 md:grid-cols-2 md:items-center">
        <div class="order-2 md:order-1">
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold leading-tight text-ink">{{ $d['headline'] ?? $landingPage->headline }}</h1>

            @if (! empty($d['offer_line']))
                <p class="mt-1 text-3xl sm:text-4xl md:text-5xl font-extrabold leading-tight text-brand-600">{{ $d['offer_line'] }}</p>
            @endif

            @if (! empty($d['badge_text']))
                <p class="mt-4 font-bold text-ink">{{ $d['badge_text'] }}</p>
            @endif

            @if (! empty($d['description']))
                <p class="mt-3 text-sm md:text-base leading-relaxed text-brand-600 whitespace-pre-line">{{ $d['description'] }}</p>
            @endif

            @if ($bullets)
                <ul class="mt-3 space-y-1.5 text-sm md:text-base text-ink">
                    @foreach ($bullets as $line)
                        <li class="flex gap-2"><span class="text-brand-500" aria-hidden="true">✔</span><span>{{ $line }}</span></li>
                    @endforeach
                </ul>
            @endif

            @if ($button)
                <a href="#order-form" class="mt-5 inline-flex items-center gap-2 rounded-md bg-brand-600 hover:bg-brand-500 text-white font-bold px-6 py-3 shadow transition">
                    <span aria-hidden="true">🛒</span> {{ $button }}
                </a>
            @endif
        </div>

        @if ($images)
            <div class="order-1 md:order-2">
                @include('landing.blocks._hero-media', ['images' => $images, 'video' => $video, 'aspect' => 'aspect-[4/3] md:aspect-square', 'wrapClass' => 'border-0! rounded-2xl! shadow-xl'])
            </div>
        @endif
    </div>

    <svg viewBox="0 0 1440 90" preserveAspectRatio="none" class="mt-8 block h-10 w-full text-brand-600 md:h-20" aria-hidden="true">
        <path fill="currentColor" d="M0,50 C220,100 470,0 740,40 C1000,80 1220,70 1440,20 L1440,90 L0,90 Z"/>
    </svg>
</section>
