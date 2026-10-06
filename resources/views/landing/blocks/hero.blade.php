{{-- Hero — default look (used by the purple template): dark band with the
     headline / offer pill / button, then a panel with the slider and the
     feature list. green and cream have their own hero/{tpl}.blade.php. --}}
@php
    $images = array_values(array_filter((array) ($d['images'] ?? [])));
    if (! $images && ! empty($d['image'])) {
        $images = [$d['image']];
    }
    $video = \App\Support\VideoEmbed::parse($d['video_url'] ?? null);
    $button = $d['button_text'] ?? 'অর্ডার করুন';
    $bullets = array_values(array_filter(array_map(fn ($b) => is_array($b) ? ($b['text'] ?? null) : $b, (array) ($d['bullets'] ?? []))));
@endphp

<section class="bg-brand-600 text-white text-center px-4 pt-7 pb-8">
    <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold leading-snug max-w-3xl mx-auto">{{ $d['headline'] ?? $landingPage->headline }}</h1>

    @if (! empty($d['offer_line']))
        <div class="mt-4 inline-block max-w-full bg-white/90 text-brand-600 font-bold rounded-lg border-2 border-secondary px-4 py-2 text-base sm:text-lg md:text-xl">
            {{ $d['offer_line'] }}
        </div>
    @endif

    @if ($button)
        <div class="mt-4">
            <a href="#order-form" class="inline-flex items-center gap-2 rounded-lg bg-secondary text-brand-600 font-bold px-6 py-2.5 shadow hover:brightness-95 transition">
                <span aria-hidden="true">🛒</span> {{ $button }}
            </a>
        </div>
    @endif
</section>

<section class="bg-secondary/20 px-4 py-6 md:py-10">
    <div class="max-w-5xl mx-auto grid gap-5 md:grid-cols-2 md:items-start">
        @include('landing.blocks._hero-media', ['images' => $images, 'video' => $video])

        <div class="@if (! $images) md:col-span-2 max-w-2xl mx-auto w-full @endif">
            @if (! empty($d['badge_text']))
                <h2 class="text-xl md:text-2xl font-extrabold text-brand-600 text-center md:text-left">{{ $d['badge_text'] }}</h2>
            @endif

            @if (! empty($d['description']))
                <p class="mt-2 text-sm md:text-base text-ink leading-relaxed">{{ $d['description'] }}</p>
            @endif

            @if ($bullets)
                <div class="mt-3 rounded-xl border-2 border-dashed border-secondary bg-white p-4">
                    @if (! empty($d['list_heading']))
                        <h3 class="font-bold text-lg text-brand-600 mb-2">{{ $d['list_heading'] }}</h3>
                    @endif
                    <ul class="space-y-2 text-sm md:text-base text-ink">
                        @foreach ($bullets as $line)
                            <li class="flex gap-2"><span class="mt-0.5 text-brand-500" aria-hidden="true">✔</span><span>{{ $line }}</span></li>
                        @endforeach
                    </ul>
                    @if ($button)
                        <a href="#order-form" class="mt-4 flex items-center justify-center gap-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold py-2.5 transition">
                            <span aria-hidden="true">🛒</span> {{ $button }}
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
