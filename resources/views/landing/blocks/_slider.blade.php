{{-- Autoplay image slider (Alpine). Expects $images (list of URLs); optional $alt, $aspect, $slot. --}}
@php($images = array_values(array_filter((array) $images)))
@if ($images)
    <div x-data="{ i: 0, n: {{ count($images) }}, t: null,
                   init() { if (this.n > 1) this.t = setInterval(() => this.i = (this.i + 1) % this.n, 4500) },
                   go(d) { clearInterval(this.t); this.i = (this.i + d + this.n) % this.n } }"
         class="relative overflow-hidden rounded-xl border-2 border-dashed border-brand-200 bg-white {{ $wrapClass ?? '' }}">
        <div class="flex transition-transform duration-500 ease-out" :style="`transform: translateX(-${i * 100}%)`">
            @foreach ($images as $image)
                <img src="{{ $image }}" alt="{{ $alt ?? '' }}" class="w-full shrink-0 {{ $aspect ?? 'aspect-square' }} object-cover"
                     @if (! $loop->first) loading="lazy" @endif>
            @endforeach
        </div>

        @if (count($images) > 1)
            <button type="button" @click="go(-1)" aria-label="আগের ছবি"
                    class="absolute left-2 top-1/2 -translate-y-1/2 h-8 w-8 rounded-full bg-brand-500/90 text-white flex items-center justify-center">‹</button>
            <button type="button" @click="go(1)" aria-label="পরের ছবি"
                    class="absolute right-2 top-1/2 -translate-y-1/2 h-8 w-8 rounded-full bg-brand-500/90 text-white flex items-center justify-center">›</button>
            <div class="absolute bottom-2 inset-x-0 flex justify-center gap-1.5">
                @foreach ($images as $k => $image)
                    <span class="h-1.5 rounded-full transition-all" :class="i === {{ $k }} ? 'w-4 bg-brand-500' : 'w-1.5 bg-white/80'"></span>
                @endforeach
            </div>
        @endif

        {{ $slot ?? '' }}
    </div>
@endif
