@php
    $phone = trim((string) ($d['phone'] ?? ''));
    $tel = $phone !== '' ? preg_replace('/[^0-9+]/', '', $phone) : '';
    $button = $d['button_text'] ?? '';
@endphp
<section class="bg-white px-4 py-7 text-center border-y border-secondary/40">
    <div class="max-w-3xl mx-auto">
        @if (! empty($d['heading']) || $phone !== '')
            <p class="text-xl md:text-3xl font-extrabold text-ink leading-snug">
                {{ $d['heading'] ?? '' }}
                @if ($phone !== '')
                    <a href="tel:{{ $tel }}" class="text-brand-600 whitespace-nowrap">{{ $phone }}</a>
                @endif
            </p>
        @endif
        @if ($button)
            <a href="#order-form" class="mt-4 inline-flex items-center gap-2 rounded-full bg-brand-500 hover:bg-brand-600 text-white font-bold px-8 py-3 text-lg shadow-md transition">
                <span aria-hidden="true">🛒</span> {{ $button }}
            </a>
        @endif
    </div>
</section>
