@if($heroBanners->isNotEmpty())
    <h1 class="sr-only">{{ $settings::get('hero_title') ?: $settings::siteName().' — '.$settings::siteNameEn() }}</h1>
@endif

<section class="grid grid-cols-1 gap-3 pt-4 pb-2 md:gap-4 md:py-6 lg:grid-cols-[2fr_1fr]">
    {{-- Hero: banners swipe on phones (scroll-snap) and auto-advance; text card when there are none. --}}
    <div class="relative overflow-hidden rounded-2xl bg-gray-100">
        @if($heroBanners->isNotEmpty())
            <div x-data="{
                    i: 0,
                    total: {{ $heroBanners->count() }},
                    timer: null,
                    go(n) { this.i = (n + this.total) % this.total; this.$refs.track.scrollTo({ left: this.i * this.$refs.track.clientWidth, behavior: 'smooth' }); },
                    sync() { this.i = Math.round(this.$refs.track.scrollLeft / Math.max(1, this.$refs.track.clientWidth)); },
                    play() { if (this.total > 1 && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches) this.timer = setInterval(() => this.go(this.i + 1), 5000); },
                    stop() { clearInterval(this.timer); },
                }" x-init="play()" @touchstart.passive="stop()" @mouseenter="stop()" @mouseleave="stop(); play()">
                <div x-ref="track" @scroll.debounce.80ms="sync()" class="flex snap-x snap-mandatory overflow-x-auto scrollbar-none" aria-roledescription="carousel" aria-label="ব্যানার">
                    @foreach($heroBanners as $idx => $banner)
                        <a href="{{ $banner->link ?: '#' }}" @unless($banner->link) tabindex="-1" @endunless
                            class="relative block aspect-[2/1] w-full shrink-0 snap-center snap-always lg:aspect-auto lg:h-[340px]"
                            aria-label="ব্যানার {{ $idx + 1 }} / {{ $heroBanners->count() }}">
                            <img src="{{ $banner->image }}" alt="{{ $banner->title ?? '' }}" width="1200" height="600" @if($idx === 0) fetchpriority="high" @else loading="lazy" @endif class="absolute inset-0 h-full w-full object-cover" draggable="false">
                        </a>
                    @endforeach
                </div>
                @if($heroBanners->count() > 1)
                    <div class="absolute inset-x-0 bottom-2.5 flex justify-center gap-1.5">
                        @foreach($heroBanners as $idx => $banner)
                            <button type="button" @click="stop(); go({{ $idx }})" aria-label="ব্যানার {{ $idx + 1 }}"
                                class="flex h-6 items-center px-0.5"><span class="block h-1.5 rounded-full bg-white shadow transition-all" :class="i === {{ $idx }} ? 'w-5' : 'w-1.5 opacity-60'"></span></button>
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            <div class="flex min-h-[200px] items-center justify-center bg-gradient-to-br from-brand-100 to-brand-50 p-6 text-center lg:min-h-[340px] lg:p-10">
                <div>
                    <h1 class="text-xl font-bold text-ink sm:text-2xl lg:text-3xl">{{ $settings::get('hero_title') ?: 'খাঁটি ও প্রাকৃতিক পণ্যের অনলাইন দোকান' }}</h1>
                    <p class="mt-2 text-sm text-gray-600 sm:text-base">{{ $settings::get('hero_subtitle') ?: 'মধু, সরিষার তেল, ঘি, খেজুর — সরাসরি আপনার দোরগোড়ায়' }}</p>
                    <a href="{{ route('shop') }}" class="mt-5 inline-flex h-11 items-center rounded-xl bg-brand-500 px-6 text-sm font-semibold text-white hover:bg-brand-600">কেনাকাটা শুরু করুন</a>
                </div>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-2 gap-3 md:gap-4 lg:grid-cols-1 lg:grid-rows-2">
        @foreach([0, 1] as $i)
            @php
                $side = $sideBanners[$i] ?? null;
                $tag = $side && $side->link ? 'a' : 'div';
            @endphp
            <{{ $tag }} @if($tag === 'a') href="{{ $side->link }}" @endif class="relative flex min-h-[96px] items-center justify-center overflow-hidden rounded-2xl bg-brand-50 p-3 text-center sm:min-h-[120px]">
                @if($side)
                    <img src="{{ $side->image }}" alt="{{ $side->title ?? '' }}" width="600" height="300" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
                @else
                    <div>
                        <p class="text-sm font-semibold text-gray-800 sm:text-base">{{ $settings::get('side_card_'.($i + 1).'_title') ?: ($i === 0 ? 'সেরা মানের মধু' : 'ফ্রি ডেলিভারি') }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">{{ $settings::get('side_card_'.($i + 1).'_text') ?: ($i === 0 ? 'সরাসরি চাষী থেকে, ১০০% খাঁটি' : 'নির্দিষ্ট পরিমাণ কেনাকাটায়') }}</p>
                    </div>
                @endif
            </{{ $tag }}>
        @endforeach
    </div>
</section>
