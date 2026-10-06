{{-- CTA — default look (purple): white band, large bold line with the phone
     number in the same weight ("বিশেষ প্রয়োজনে কল করুনঃ 01…"), optional button. --}}
@php
    $phone = trim((string) ($d['phone'] ?? ''));
    $tel = $phone !== '' ? preg_replace('/[^0-9+]/', '', $phone) : '';
    $button = $d['button_text'] ?? '';
@endphp
<section class="bg-white px-4 py-7 text-center">
    <div class="max-w-3xl mx-auto">
        @if (! empty($d['heading']) || $phone !== '')
            <p class="text-xl md:text-3xl font-extrabold text-ink leading-snug">
                {{ $d['heading'] ?? '' }}
                @if ($phone !== '')
                    <a href="tel:{{ $tel }}" class="whitespace-nowrap">{{ $phone }}</a>
                @endif
            </p>
        @endif
        @if (! empty($d['subtext']))
            <p class="mt-2 text-sm md:text-base text-gray-600">{{ $d['subtext'] }}</p>
        @endif
        @if ($button)
            <a href="#order-form" class="mt-4 inline-flex items-center gap-2 rounded-md bg-brand-600 px-8 py-3 text-lg font-bold text-white shadow hover:bg-brand-500 transition">
                <span aria-hidden="true">🛒</span> {{ $button }}
            </a>
        @endif
    </div>
</section>
