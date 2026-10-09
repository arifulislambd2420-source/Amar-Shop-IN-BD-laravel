@if($blogs->isNotEmpty())
    <section class="py-5 md:py-8">
        <div class="mb-3 flex items-center justify-between md:mb-4">
            <h2 class="text-lg font-bold md:text-xl">ব্লগ</h2>
            <a href="{{ route('blog.index') }}" class="-my-2 inline-flex min-h-10 items-center text-sm font-medium text-brand-600">সব দেখুন →</a>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-5">
            @foreach($blogs as $b)
                <a href="{{ route('blog.show', $b->slug) }}" class="overflow-hidden rounded-2xl border border-gray-200 bg-white transition-shadow hover:shadow-md">
                    <div class="aspect-video bg-gray-100">
                        @if($b->cover)
                            <img src="{{ $b->cover }}" alt="" width="400" height="225" loading="lazy" decoding="async" class="h-full w-full object-cover">
                        @endif
                    </div>
                    <div class="p-3">
                        <h3 class="line-clamp-2 text-sm font-semibold">{{ $b->title }}</h3>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif
