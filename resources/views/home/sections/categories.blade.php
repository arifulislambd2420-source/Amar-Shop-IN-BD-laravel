@if($categories->isNotEmpty())
    <section class="py-5 md:py-8" aria-labelledby="home-cats">
        <div class="mb-3 flex items-center justify-between md:mb-4">
            <h2 id="home-cats" class="text-lg font-bold md:text-xl">ক্যাটাগরি</h2>
            <a href="{{ route('shop') }}" class="-my-2 inline-flex min-h-10 items-center text-sm font-medium text-brand-600">সব দেখুন →</a>
        </div>
        <div class="swipe-row -mx-4 gap-3 px-4 pb-1 md:mx-0 md:grid md:grid-cols-6 md:gap-4 md:overflow-visible md:px-0 lg:grid-cols-8">
            @foreach($categories as $c)
                <x-category-card :category="$c" :href="route('shop', ['category' => $c->slug])" class="swipe-half md:w-auto md:min-w-0 md:flex-none" />
            @endforeach
        </div>
    </section>
@endif
