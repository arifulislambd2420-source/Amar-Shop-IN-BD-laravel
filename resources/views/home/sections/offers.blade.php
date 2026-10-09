@if($discounted->isNotEmpty())
    <section class="py-5 md:py-8">
        <div class="mb-3 flex items-center justify-between gap-3 md:mb-4">
            <h2 class="text-lg font-bold md:text-xl">বিশেষ <span class="text-brand-600">ছাড়</span></h2>
            <a href="{{ route('offers') }}" class="-my-2 inline-flex min-h-10 shrink-0 items-center text-sm font-medium text-brand-600">সব দেখুন →</a>
        </div>
        <div class="{{ $grid }}">
            @foreach($discounted as $p)
                <x-product-card :product="$p" />
            @endforeach
        </div>
    </section>
@endif
