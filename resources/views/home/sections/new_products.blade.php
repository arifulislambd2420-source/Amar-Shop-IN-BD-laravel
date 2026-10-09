@if($products->isNotEmpty())
    <section class="py-5 md:py-8">
        <div class="mb-3 flex items-center justify-between gap-3 md:mb-4">
            <h2 class="text-lg font-bold md:text-xl">নতুন পণ্য</h2>
            <a href="{{ route('shop') }}" class="-my-2 inline-flex min-h-10 shrink-0 items-center text-sm font-medium text-brand-600">সব দেখুন →</a>
        </div>
        <div class="{{ $grid }}">
            @foreach($products as $p)
                <x-product-card :product="$p" />
            @endforeach
        </div>
    </section>
@else
    <section class="py-10">
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center">
            <p class="text-lg font-semibold text-gray-800">শিগগিরই নতুন পণ্য আসছে</p>
            <p class="mt-1 text-sm text-gray-500">একটু পরে আবার দেখুন।</p>
        </div>
    </section>
@endif
