{{-- CTA — green: full-width dark band, white button. --}}
@php
    $phone = trim((string) ($d['phone'] ?? ''));
    $tel = $phone !== '' ? preg_replace('/[^0-9+]/', '', $phone) : '';
    $button = $d['button_text'] ?? '';
@endphp
<section class="bg-brand-600 px-4 py-9 text-center text-white md:py-12">
    <div class="max-w-3xl mx-auto">
        @if (! empty($d['heading']))
            <h2 class="text-2xl md:text-4xl font-extrabold leading-snug">{{ $d['heading'] }}</h2>
        @endif
        @if (! empty($d['subtext']))
            <p class="mt-3 text-sm md:text-lg text-white/90 leading-relaxed">{{ $d['subtext'] }}</p>
        @endif
        @if ($phone !== '')
            <p class="mt-3 text-lg font-bold"><a href="tel:{{ $tel }}">{{ $phone }}</a></p>
        @endif
        @if ($button)
            <a href="#order-form" class="mt-5 inline-flex items-center gap-2 rounded-md bg-white px-6 py-3 font-bold text-brand-600 shadow hover:bg-secondary/30 transition">
                <span aria-hidden="true">🛒</span> {{ $button }}
            </a>
        @endif
    </div>
</section>
