@if($brands->isNotEmpty())
    <section class="py-5 md:py-8">
        <div class="mb-3 flex items-center justify-between md:mb-4">
            <h2 class="text-lg font-bold md:text-xl">ব্র্যান্ডসমূহ</h2>
            <a href="{{ route('brands') }}" class="-my-2 inline-flex min-h-10 items-center text-sm font-medium text-brand-600">সব দেখুন →</a>
        </div>
        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 md:gap-4 md:p-6">
            @foreach($brands as $b)
                <a href="{{ route('shop', ['brand' => $b->id]) }}" class="inline-flex min-h-11 items-center rounded-xl px-2 hover:bg-gray-50" aria-label="{{ $b->name }}">
                    @if($b->logo)
                        <img src="{{ $b->logo }}" alt="{{ $b->name }}" width="120" height="40" loading="lazy" class="h-9 w-auto max-w-[120px] object-contain grayscale transition hover:grayscale-0">
                    @else
                        <span class="rounded-full border border-gray-200 px-3 py-1.5 text-sm font-semibold text-gray-600">{{ $b->name }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </section>
@endif
