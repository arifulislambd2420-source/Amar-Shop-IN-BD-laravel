@foreach($showcase as $block)
    @continue($block['products']->isEmpty())
    <section class="py-5 md:py-8">
        <div class="mb-3 flex items-center justify-between gap-3 md:mb-4">
            <h2 class="min-w-0 truncate text-lg font-bold md:text-xl">{{ $block['category']->name }}</h2>
            <a href="{{ route('shop', ['category' => $block['category']->slug]) }}" class="-my-2 inline-flex min-h-10 shrink-0 items-center text-sm font-medium text-brand-600">সব দেখুন →</a>
        </div>
        <div class="{{ $grid }}">
            @foreach($block['products'] as $p)
                <x-product-card :product="$p" />
            @endforeach
        </div>
    </section>
@endforeach
