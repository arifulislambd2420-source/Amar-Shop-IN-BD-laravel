@if($activeFlashSale && $activeFlashSale->items->isNotEmpty())
    <section class="py-5 md:py-8">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-secondary p-4 text-white md:p-5">
            <div class="min-w-0">
                <h2 class="text-lg font-bold md:text-xl">⚡ {{ $activeFlashSale->title }}</h2>
                <p class="text-sm text-white/70">সীমিত সময়ের অফার — শেষ হবে {{ $activeFlashSale->end_time->locale('bn')->translatedFormat('j F, g:i A') }}</p>
            </div>
        </div>
        <div class="{{ $grid }}">
            @foreach($activeFlashSale->items as $item)
                @if($item->product)
                    <x-product-card :product="$item->product" />
                @endif
            @endforeach
        </div>
    </section>
@endif
