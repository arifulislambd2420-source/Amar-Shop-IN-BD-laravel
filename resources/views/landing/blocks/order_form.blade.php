@php
    $waNumber = \App\Support\SiteSettingsHelper::whatsapp();
    $waText = rawurlencode($landingPage->title.' — অর্ডার করতে চাই। '.url()->current());
@endphp
<section id="order-form" class="bg-brand-600 px-4 py-8 md:py-12">
    <div class="max-w-3xl mx-auto">
        @if (! empty($d['heading']))
            <h2 class="mb-5 rounded-lg border-2 border-secondary bg-secondary/30 px-4 py-3 text-center text-lg md:text-2xl font-extrabold text-brand-600 shadow">{{ $d['heading'] }}</h2>
        @endif

        <div class="rounded-2xl bg-white p-4 md:p-6 shadow-xl">
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

        @if ($waNumber)
            <a href="https://wa.me/{{ $waNumber }}?text={{ $waText }}" target="_blank" rel="noopener noreferrer"
               class="mt-4 flex items-center justify-center gap-2 rounded-lg bg-[#25D366] hover:brightness-95 text-white font-bold py-3 shadow transition">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.29-.15-1.71-.85-1.98-.94-.27-.1-.46-.15-.66.15-.2.29-.76.94-.93 1.13-.17.2-.34.22-.63.07-.29-.15-1.22-.45-2.32-1.43-.86-.76-1.44-1.71-1.6-2-.17-.29-.02-.45.13-.6.13-.13.29-.34.44-.51.15-.17.2-.29.29-.49.1-.2.05-.37-.02-.51-.07-.15-.66-1.59-.9-2.18-.24-.57-.48-.5-.66-.51h-.56c-.2 0-.51.07-.78.37-.27.29-1.02 1-1.02 2.43 0 1.43 1.04 2.82 1.19 3.01.15.2 2.05 3.13 4.97 4.39.69.3 1.23.48 1.65.61.69.22 1.32.19 1.82.11.55-.08 1.71-.7 1.95-1.38.24-.68.24-1.26.17-1.38-.07-.12-.27-.2-.56-.34z"/><path d="M12.01 2C6.49 2 2 6.48 2 11.99c0 1.94.55 3.75 1.5 5.29L2 22l4.86-1.44c1.48.81 3.18 1.28 4.99 1.28h.01c5.52 0 10.01-4.48 10.01-9.99C21.87 6.48 17.53 2 12.01 2z"/></svg>
                WhatsApp-এ অর্ডার করুন
            </a>
        @endif
    </div>
</section>
