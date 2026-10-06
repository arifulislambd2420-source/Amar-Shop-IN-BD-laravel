{{-- Reviews — default look (purple): dark band, heading, review screenshots in a
     slider with arrows (swipeable on mobile), optional order button. --}}
@php
    $images = array_values(array_filter((array) ($d['images'] ?? [])));
    $button = $d['button_text'] ?? '';
@endphp
@if ($images)
    <section class="bg-brand-600 px-4 py-8 md:py-10">
        <div class="max-w-5xl mx-auto" x-data="{ scroll(d) { const t = $refs.track; t.scrollBy({ left: d * t.clientWidth * 0.8, behavior: 'smooth' }) } }">
            @if (! empty($d['heading']))
                <h2 class="mb-5 text-center text-lg md:text-3xl font-extrabold text-white">
                    @if ($tpl === 'purple')<span aria-hidden="true">🌟</span>@endif {{ $d['heading'] }} @if ($tpl === 'purple')<span aria-hidden="true">🌟</span>@endif
                </h2>
            @endif

            <div class="relative rounded-xl bg-white p-3">
                <div x-ref="track" class="flex gap-3 overflow-x-auto snap-x snap-mandatory scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach ($images as $image)
                        <img src="{{ $image }}" alt="কাস্টমার রিভিউ" loading="lazy"
                             class="snap-center shrink-0 w-[88%] sm:w-[48%] md:w-[32%] rounded-lg border border-gray-200 object-contain">
                    @endforeach
                </div>

                @if (count($images) > 1)
                    <button type="button" @click="scroll(-1)" aria-label="আগের"
                            class="absolute left-1 top-1/2 -translate-y-1/2 flex h-8 w-8 items-center justify-center rounded-full bg-brand-600 text-white shadow">‹</button>
                    <button type="button" @click="scroll(1)" aria-label="পরের"
                            class="absolute right-1 top-1/2 -translate-y-1/2 flex h-8 w-8 items-center justify-center rounded-full bg-brand-600 text-white shadow">›</button>
                @endif
            </div>

            @if ($button)
                <div class="mt-4 text-center">
                    <a href="#order-form" class="inline-flex items-center gap-2 rounded-md bg-secondary px-6 py-2.5 font-bold text-brand-600 shadow hover:brightness-95 transition">
                        <span aria-hidden="true">🛒</span> {{ $button }}
                    </a>
                </div>
            @endif
        </div>
    </section>
@endif
