{{-- Variants. Sizes/colours become pickers inside the order form (see
     LandingOrderForm::variantConfig); hiding this block removes them. When
     "options" (image + name) are set, this also renders a colour/design grid
     where every card has its own order button that preselects that colour. --}}
@php
    $options = array_values(array_filter((array) ($d['options'] ?? []), fn ($o) => filled($o['name'] ?? null)));
    $button = $d['button_text'] ?? 'অর্ডার করুন';
@endphp
@if ($options)
    <section class="bg-white px-4 py-10 md:py-14">
        <div class="max-w-6xl mx-auto">
            <div class="mb-6 text-center">
                @if (! empty($d['label']))
                    <span class="inline-block rounded-full bg-secondary/15 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-secondary">{{ $d['label'] }}</span>
                @endif
                @if (! empty($d['heading']))
                    <h2 class="mt-2 text-2xl md:text-4xl font-extrabold leading-tight text-ink">
                        {{ $d['heading'] }}
                        @if (! empty($d['accent']))
                            <span class="text-secondary">{{ $d['accent'] }}</span>
                        @endif
                    </h2>
                @endif
                @if (! empty($d['description']))
                    <p class="mx-auto mt-2 max-w-2xl text-sm md:text-base text-gray-500 leading-relaxed">{{ $d['description'] }}</p>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-5">
                @foreach ($options as $option)
                    <div class="overflow-hidden rounded-xl bg-white shadow-md ring-1 ring-gray-100">
                        @if (! empty($option['image']))
                            <img src="{{ $option['image'] }}" alt="{{ $option['name'] }}" loading="lazy" class="aspect-[3/4] w-full object-cover">
                        @endif
                        <div class="p-2.5 text-center">
                            <p class="text-sm font-semibold text-ink leading-snug">{{ $option['name'] }}</p>
                            @if ($button)
                                <a href="#order-form"
                                   onclick="window.Livewire && Livewire.dispatch('select-variant', { color: @js($option['name']) })"
                                   class="mt-2 flex items-center justify-center gap-1.5 rounded-md bg-brand-600 py-2 text-xs md:text-sm font-bold text-white hover:bg-brand-500 transition">
                                    <span aria-hidden="true">🛒</span> {{ $button }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
