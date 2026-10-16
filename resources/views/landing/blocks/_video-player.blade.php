{{-- A parsed VideoEmbed ($video) at full width, 16:9 — and nothing from YouTube/Facebook/the video file is
     loaded until the visitor taps play (a cover picture + play button stands in), so the page stays fast.
     Optional: $title, $poster (cover picture), $class (shape, default rounded), $autoload (start at once, e.g. in a popup). --}}
@php
    $cover = ! empty($poster) ? $poster : ($video['thumb'] ?? null);
    $label = $title ?? 'Video';
@endphp
<div x-data="{ on: {{ ! empty($autoload) ? 'true' : 'false' }} }"
     class="relative w-full aspect-video overflow-hidden bg-black {{ $class ?? 'rounded-xl' }}">
    <template x-if="on">
        @if ($video['type'] === 'iframe')
            <iframe src="{{ $video['play'] }}" class="absolute inset-0 h-full w-full border-0"
                    allow="accelerometer; autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen
                    referrerpolicy="strict-origin-when-cross-origin" title="{{ $label }}"></iframe>
        @else
            <video src="{{ $video['play'] }}" class="absolute inset-0 h-full w-full bg-black" controls autoplay playsinline
                   @if ($cover) poster="{{ $cover }}" @endif></video>
        @endif
    </template>

    <button type="button" x-show="! on" @click="on = true" aria-label="ভিডিও চালান: {{ $label }}"
            class="group absolute inset-0 flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-800 to-black">
        @if ($cover)
            <img src="{{ $cover }}" alt="" width="640" height="360" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover">
        @endif
        <span class="absolute inset-0 bg-black/20 transition-colors group-hover:bg-black/30"></span>
        <span class="relative flex h-16 w-16 items-center justify-center rounded-full bg-white/95 text-2xl text-brand-600 shadow-lg transition-transform group-hover:scale-105 md:h-20 md:w-20" aria-hidden="true">▶</span>
    </button>

    <noscript><a href="{{ $video['watch'] }}" rel="noopener" target="_blank" class="absolute inset-0 flex items-center justify-center text-white underline">ভিডিও দেখুন</a></noscript>
</div>
