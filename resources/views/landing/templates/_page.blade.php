@php
    // Shared body of every block-builder template (green / purple / cream).
    // The look comes from two things only: this page's own colors
    // (LandingPage::colors(): its picks, else the template default — never the
    // site-wide brand color) set as --brand / --secondary on the wrapper, and
    // the per-template block partial picked below ($tpl).
    $colors = $landingPage->colors();
    $waNumber = \App\Support\SiteSettingsHelper::whatsapp();
    $hasOrderForm = collect($landingPage->visibleBlocks())->contains(fn ($b) => ($b['type'] ?? null) === 'order_form');
    $pageBg = $tpl === 'cream' ? 'color-mix(in srgb, var(--secondary) 4%, #fffdf8)' : '#ffffff';
@endphp

<div class="text-ink pb-16 md:pb-0 overflow-x-clip"
     style="--brand: {{ $colors['primary'] }}; --secondary: {{ $colors['secondary'] }}; background: {{ $pageBg }};">
    @foreach ($landingPage->visibleBlocks() as $block)
        @includeFirst([
            'landing.blocks.'.($block['type'] ?? 'missing').'.'.$tpl,
            'landing.blocks.'.($block['type'] ?? 'missing'),
            'landing.blocks._missing',
        ], [
            'd' => $block['data'] ?? [],
            'landingPage' => $landingPage,
            'tpl' => $tpl,
        ])
    @endforeach

    @if ($waNumber)
        <a href="https://wa.me/{{ $waNumber }}?text={{ rawurlencode($landingPage->title) }}" target="_blank" rel="noopener noreferrer"
           aria-label="হোয়াটসঅ্যাপে যোগাযোগ করুন"
           class="fixed bottom-20 md:bottom-6 right-4 z-40 flex h-12 w-12 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.29-.15-1.71-.85-1.98-.94-.27-.1-.46-.15-.66.15-.2.29-.76.94-.93 1.13-.17.2-.34.22-.63.07-.29-.15-1.22-.45-2.32-1.43-.86-.76-1.44-1.71-1.6-2-.17-.29-.02-.45.13-.6.13-.13.29-.34.44-.51.15-.17.2-.29.29-.49.1-.2.05-.37-.02-.51-.07-.15-.66-1.59-.9-2.18-.24-.57-.48-.5-.66-.51h-.56c-.2 0-.51.07-.78.37-.27.29-1.02 1-1.02 2.43 0 1.43 1.04 2.82 1.19 3.01.15.2 2.05 3.13 4.97 4.39.69.3 1.23.48 1.65.61.69.22 1.32.19 1.82.11.55-.08 1.71-.7 1.95-1.38.24-.68.24-1.26.17-1.38-.07-.12-.27-.2-.56-.34z"/><path d="M12.01 2C6.49 2 2 6.48 2 11.99c0 1.94.55 3.75 1.5 5.29L2 22l4.86-1.44c1.48.81 3.18 1.28 4.99 1.28h.01c5.52 0 10.01-4.48 10.01-9.99C21.87 6.48 17.53 2 12.01 2z"/></svg>
        </a>
    @endif

    @if ($hasOrderForm)
        {{-- Mobile: always-visible jump to the order form. --}}
        <a href="#order-form"
           class="md:hidden fixed bottom-0 inset-x-0 z-30 flex items-center justify-center gap-2 bg-brand-500 py-3 text-white font-bold shadow-[0_-2px_10px_rgba(0,0,0,.15)]">
            <span aria-hidden="true">🛒</span> {{ $landingPage->button_text }}
        </a>
    @endif
</div>
