<x-filament-widgets::widget>
    {{-- Inline styles only: the admin CSS bundle is prebuilt. --}}
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.85rem 1rem; border-radius: 0.75rem; border: 1px solid rgba(220, 38, 38, .35); background: rgba(220, 38, 38, .08);">
        <div style="display: flex; align-items: center; gap: 0.6rem;">
            <span style="font-size: 1.4rem;" aria-hidden="true">⚠️</span>
            <div>
                <div style="font-weight: 700; color: rgb(185, 28, 28);">স্টক সতর্কতা</div>
                <div style="font-size: 0.85rem; opacity: .85;">
                    @if ($out > 0) {{ $out }} টি প্রোডাক্টের স্টক শেষ। @endif
                    @if ($low > 0) {{ $low }} টি প্রোডাক্টের স্টক {{ $threshold }} বা কম। @endif
                </div>
            </div>
        </div>
        <x-filament::button tag="a" :href="$url" size="sm" color="danger">প্রোডাক্ট দেখুন</x-filament::button>
    </div>
</x-filament-widgets::widget>
