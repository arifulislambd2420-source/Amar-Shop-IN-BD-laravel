{{-- Models / colours — a picture grid of what the customer can pick, each card
     with its own price, discount and an order button that preselects it in the
     order form (event "select-model"). The cards are the page's packages, or
     else the linked product's variants (colour + picture + price + stock), so
     this block never needs the same list typed twice. --}}
@php
    $packs = collect((array) $landingPage->packages)
        ->filter(fn ($p) => is_array($p) && filled($p['product_id'] ?? null) && is_numeric($p['price'] ?? null))
        ->values()
        ->map(fn ($p) => [
            'label' => (string) ($p['label'] ?? ''),
            'image' => $p['image'] ?? null,
            'price' => (float) $p['price'],
            'compare_price' => is_numeric($p['compare_price'] ?? null) ? (float) $p['compare_price'] : null,
            'stock' => 1,
        ])->all();
    $cards = $packs ?: $landingPage->modelCards();
    $button = $d['button_text'] ?? 'অর্ডার করুন';
@endphp
@if (count($cards) > 0)
    <section class="px-4 py-10 md:py-14" id="models">
        <div class="max-w-6xl mx-auto">
            <div class="mb-6 text-center">
                @if (! empty($d['label']))
                    <span class="inline-block rounded-full bg-secondary/15 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-brand-600">{{ $d['label'] }}</span>
                @endif
                @if (! empty($d['heading']))
                    <h2 class="mt-2 text-2xl md:text-4xl font-extrabold leading-tight text-ink">
                        {{ $d['heading'] }}
                        @if (! empty($d['accent']))
                            <span class="text-brand-600">{{ $d['accent'] }}</span>
                        @endif
                    </h2>
                @endif
                @if (! empty($d['description']))
                    <p class="mx-auto mt-2 max-w-2xl text-sm md:text-base text-gray-500 leading-relaxed">{{ $d['description'] }}</p>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-3 md:gap-5 {{ count($cards) >= 4 ? 'md:grid-cols-4' : (count($cards) === 3 ? 'md:grid-cols-3' : 'md:grid-cols-2 md:max-w-3xl md:mx-auto') }}">
                @foreach ($cards as $i => $card)
                    @php
                        $gone = $card['stock'] <= 0;
                        $off = $card['compare_price'] && $card['compare_price'] > $card['price']
                            ? (int) round((1 - $card['price'] / $card['compare_price']) * 100) : 0;
                    @endphp
                    <div class="relative flex flex-col overflow-hidden rounded-xl bg-white shadow-md ring-1 ring-gray-100 {{ $gone ? 'opacity-60' : '' }}">
                        @if ($off > 0)
                            <span class="absolute left-2 top-2 z-10 rounded-md bg-brand-600 px-2 py-0.5 text-xs font-bold text-white">-{{ $off }}%</span>
                        @endif
                        @if (! empty($card['image']))
                            <img width="800" height="800" src="{{ $card['image'] }}" alt="{{ $card['label'] }}" loading="lazy" class="aspect-square w-full object-cover">
                        @endif
                        <div class="flex flex-1 flex-col p-2.5 text-center">
                            <p class="text-sm font-semibold leading-snug text-ink">{{ $card['label'] }}</p>
                            <p class="mt-1 text-sm">
                                <span class="font-bold text-brand-600">@taka($card['price'])</span>
                                @if ($card['compare_price'] && $card['compare_price'] > $card['price'])
                                    <span class="ml-1 text-xs text-gray-400 line-through">@taka($card['compare_price'])</span>
                                @endif
                            </p>
                            @if ($gone)
                                <p class="mt-2 rounded-md bg-gray-100 py-2 text-xs font-semibold text-gray-400">স্টক নেই</p>
                            @elseif ($button)
                                <a href="#order-form"
                                   onclick="window.Livewire && Livewire.dispatch('select-model', { index: {{ $i }} })"
                                   class="mt-auto flex min-h-11 items-center justify-center gap-1.5 rounded-md bg-brand-600 px-2 py-2 text-xs md:text-sm font-bold text-white hover:bg-brand-500 transition" style="margin-top: .5rem">
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
