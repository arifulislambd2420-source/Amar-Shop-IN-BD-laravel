{{-- Order form — default look (purple): dark band, light pill heading, white
     card holding the two-column form, WhatsApp button. --}}
@php
    $waNumber = \App\Support\SiteSettingsHelper::whatsapp();
    $waText = rawurlencode($landingPage->title.' — অর্ডার করতে চাই। '.url()->current());
@endphp
<section id="order-form" class="bg-brand-600 px-4 py-8 md:py-12">
    <div class="max-w-5xl mx-auto">
        @if (! empty($d['heading']))
            <h2 class="mx-auto mb-5 max-w-3xl rounded-md border-2 border-secondary bg-secondary/30 px-4 py-3 text-center text-lg md:text-2xl font-extrabold text-white shadow">{{ $d['heading'] }}</h2>
        @endif
        @if (! empty($d['subtext']))
            <p class="mx-auto mb-4 max-w-2xl text-center text-sm md:text-base text-white/90">{{ $d['subtext'] }}</p>
        @endif

        <div class="rounded-2xl bg-white p-4 md:p-8 shadow-xl">
            @if ($landingPage->product || ! empty($landingPage->packages))
                @livewire('landing.landing-order-form', [
                    'landingPage' => $landingPage,
                    'heading' => '',
                    'rootId' => 'lp-order-form',
                    'buttonText' => $d['button_text'] ?? '',
                    'note' => $d['note'] ?? '',
                ], key('lp-order-'.$landingPage->id))
            @else
                <p class="text-center text-gray-500">এই পণ্যটি এই মুহূর্তে পাওয়া যাচ্ছে না।</p>
            @endif
        </div>

        @if ($waNumber)
            <a href="https://wa.me/{{ $waNumber }}?text={{ $waText }}" target="_blank" rel="noopener noreferrer"
               class="mt-4 flex items-center justify-center gap-2 rounded-md bg-[#25D366] py-3 font-bold text-white shadow hover:brightness-95 transition">
                WhatsApp-এ অর্ডার করুন
            </a>
        @endif
    </div>
</section>
