@if($categories->isNotEmpty())
    <section class="py-5 md:py-8" aria-labelledby="home-cats">
        <div class="mb-3 flex items-center justify-between md:mb-4">
            <h2 id="home-cats" class="text-lg font-bold md:text-xl">ক্যাটাগরি</h2>
            <a href="{{ route('shop') }}" class="-my-2 inline-flex min-h-10 items-center text-sm font-medium text-brand-600">সব দেখুন →</a>
        </div>
        <div class="swipe-row -mx-4 gap-3 px-4 pb-1 md:mx-0 md:grid md:grid-cols-6 md:overflow-visible md:px-0 lg:grid-cols-8">
            @foreach($categories as $c)
                <a href="{{ route('shop', ['category' => $c->slug]) }}" class="swipe-half flex flex-col items-center gap-2 rounded-2xl border border-gray-200 bg-white p-3 text-center transition-colors hover:border-brand-500 hover:text-brand-600 md:w-auto md:min-w-0 md:flex-none">
                    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-50">
                        @if(\App\Filament\Resources\Categories\CategoryResource::isImageIcon($c->icon))
                            <img src="{{ $c->icon }}" alt="" width="28" height="28" loading="lazy" class="h-7 w-7 object-contain">
                        @else
                            <span class="text-2xl leading-none">{{ $c->icon ?: '🛍️' }}</span>
                        @endif
                    </span>
                    <span class="line-clamp-2 text-xs font-medium leading-tight sm:text-sm">{{ $c->name }}</span>
                </a>
            @endforeach
        </div>
    </section>
@endif
