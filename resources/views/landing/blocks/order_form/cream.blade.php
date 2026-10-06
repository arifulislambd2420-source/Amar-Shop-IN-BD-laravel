{{-- Order form — cream: dashed warm intro box (call + WhatsApp) above a bordered form card. --}}
@php
    $waNumber = \App\Support\SiteSettingsHelper::whatsapp();
    $waText = rawurlencode($landingPage->title.' — অর্ডার করতে চাই। '.url()->current());
    $phone = trim((string) ($d['phone'] ?? ''));
    $tel = $phone !== '' ? preg_replace('/[^0-9+]/', '', $phone) : '';
@endphp
<section id="order-form" class="px-4 py-8 md:py-12">
    <div class="max-w-4xl mx-auto space-y-5">
        <div class="rounded-2xl border-2 border-dashed border-secondary/50 bg-secondary/10 px-5 py-7 text-center">
            @if (! empty($d['pill']))
                <span class="mb-2 inline-block rounded-full bg-white px-3 py-1 text-xs font-semibold text-secondary ring-1 ring-secondary/30">{{ $d['pill'] }}</span>
            @endif
            @if (! empty($d['heading']))
                <h2 class="text-xl md:text-3xl font-extrabold leading-snug text-ink">{{ $d['heading'] }}</h2>
            @endif
            @if (! empty($d['subtext']))
                <p class="mx-auto mt-2 max-w-2xl text-sm md:text-base text-gray-500 leading-relaxed">{{ $d['subtext'] }}</p>
            @endif
            @if ($phone !== '' || $waNumber)
                <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                    @if ($phone !== '')
                        <a href="tel:{{ $tel }}" class="inline-flex items-center gap-2 rounded-full bg-white px-5 py-2 font-bold text-ink shadow-sm ring-1 ring-gray-200">
                            <span aria-hidden="true">📞</span> {{ $phone }}
                        </a>
                    @endif
                    @if ($waNumber)
                        <a href="https://wa.me/{{ $waNumber }}?text={{ $waText }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-2 rounded-full bg-[#25D366] px-5 py-2 font-bold text-white shadow-sm hover:brightness-95 transition">
                            WhatsApp-এ অর্ডার করুন
                        </a>
                    @endif
                </div>
            @endif
        </div>

        <div class="rounded-2xl border-2 border-secondary/40 bg-white p-4 shadow-sm md:p-8">
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
