{{-- Order form — green: dark rounded panel (badge, heading, text, WhatsApp) with
     the white form attached underneath. --}}
@php
    $waNumber = \App\Support\SiteSettingsHelper::whatsapp();
    $waText = rawurlencode($landingPage->title.' — অর্ডার করতে চাই। '.url()->current());
@endphp
<section id="order-form" class="px-4 py-8 md:py-12">
    <div class="max-w-5xl mx-auto overflow-hidden rounded-2xl border-2 border-brand-600 bg-white shadow-xl">
        <div class="bg-brand-600 px-5 py-8 text-center text-white md:py-10">
            @if (! empty($d['pill']))
                <span class="mb-3 inline-block rounded-full bg-white/15 px-4 py-1 text-xs md:text-sm font-semibold ring-1 ring-white/30">{{ $d['pill'] }}</span>
            @endif
            @if (! empty($d['heading']))
                <h2 class="text-2xl md:text-4xl font-extrabold leading-snug">{{ $d['heading'] }}</h2>
            @endif
            @if (! empty($d['subtext']))
                <p class="mx-auto mt-3 max-w-2xl text-sm md:text-base text-white/90 leading-relaxed">{{ $d['subtext'] }}</p>
            @endif
            @if ($waNumber)
                <a href="https://wa.me/{{ $waNumber }}?text={{ $waText }}" target="_blank" rel="noopener noreferrer"
                   class="mt-5 inline-flex items-center gap-2 rounded-full bg-[#25D366] px-6 py-2.5 font-bold text-white shadow hover:brightness-95 transition">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.01 2C6.49 2 2 6.48 2 11.99c0 1.94.55 3.75 1.5 5.29L2 22l4.86-1.44c1.48.81 3.18 1.28 4.99 1.28h.01c5.52 0 10.01-4.48 10.01-9.99C21.87 6.48 17.53 2 12.01 2z"/></svg>
                    WhatsApp-এ অর্ডার করুন
                </a>
            @endif
        </div>

        <div class="p-4 md:p-8">
            @if ($landingPage->product || ! empty($landingPage->packages))
                @livewire('landing.landing-order-form', [
                    'landingPage' => $landingPage,
                    'heading' => '',
                    'rootId' => 'lp-order-form',
                    'buttonText' => $d['button_text'] ?? null,
                    'note' => $d['note'] ?? '',
                ], key('lp-order-'.$landingPage->id))
            @else
                <p class="text-center text-gray-500">এই পণ্যটি এই মুহূর্তে পাওয়া যাচ্ছে না।</p>
            @endif
        </div>
    </div>
</section>
