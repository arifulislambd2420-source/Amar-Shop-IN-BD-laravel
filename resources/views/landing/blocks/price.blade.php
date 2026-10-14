{{-- Price & discount — the big price, the crossed-out regular price, how much
     the customer saves and an order button. Prices come from the page (its own
     price, or the linked product), never typed again here. --}}
@php
    $now = $landingPage->effectivePrice();
    $cardsPrices = collect($landingPage->modelCards())->pluck('price');
    $from = $landingPage->price_override === null && $cardsPrices->isNotEmpty() && $cardsPrices->unique()->count() > 1 ? $cardsPrices->min() : $now;
    $regular = (float) ($landingPage->product?->price ?? 0);
    $save = $regular > $from ? $regular - $from : 0;
    $off = $save > 0 ? (int) round($save / $regular * 100) : 0;
    $button = $d['button_text'] ?? 'অর্ডার করুন';
@endphp
@if ($from > 0)
    <section class="px-4 py-8 md:py-12">
        <div class="mx-auto max-w-2xl rounded-2xl border-2 border-brand-600 bg-white p-6 text-center shadow-xl">
            @if (! empty($d['label']))
                <span class="inline-block rounded-full bg-brand-600 px-4 py-1 text-xs font-bold text-white">{{ $d['label'] }}</span>
            @endif
            @if (! empty($d['heading']))
                <h2 class="mt-3 text-xl md:text-3xl font-extrabold text-ink">{{ $d['heading'] }}</h2>
            @endif
            <div class="mt-4 flex flex-wrap items-baseline justify-center gap-x-3 gap-y-1">
                @if ($save > 0)
                    <span class="text-lg text-gray-400 line-through">@taka($regular)</span>
                @endif
                <span class="text-4xl md:text-5xl font-extrabold text-brand-600">@taka($from)</span>
                @if ($off > 0)
                    <span class="rounded-md bg-secondary/25 px-2 py-0.5 text-sm font-bold text-ink">{{ $off }}% ছাড়</span>
                @endif
            </div>
            @if ($save > 0)
                <p class="mt-2 text-sm font-semibold text-success-600">আপনি বাঁচাচ্ছেন @taka($save)</p>
            @endif
            @if (! empty($d['note']))
                <p class="mt-2 text-sm text-gray-500">{{ $d['note'] }}</p>
            @endif
            @if ($button)
                <a href="#order-form" class="mt-5 inline-flex min-h-12 items-center justify-center gap-2 rounded-md bg-brand-600 px-8 py-3 font-bold text-white shadow transition hover:bg-brand-500">
                    <span aria-hidden="true">🛒</span> {{ $button }}
                </a>
            @endif
        </div>
    </section>
@endif
