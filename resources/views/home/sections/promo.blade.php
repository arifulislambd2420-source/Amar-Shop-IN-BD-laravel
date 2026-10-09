@if($promoBanners->isNotEmpty())
    <section class="grid grid-cols-1 gap-3 py-5 md:grid-cols-2 md:gap-4 md:py-8">
        @foreach($promoBanners->take(2) as $banner)
            @php $tag = $banner->link ? 'a' : 'div'; @endphp
            <{{ $tag }} @if($banner->link) href="{{ $banner->link }}" @endif class="block aspect-[5/2] overflow-hidden rounded-2xl bg-gray-100 md:aspect-auto md:h-48">
                <img src="{{ $banner->image }}" alt="{{ $banner->title ?? '' }}" width="800" height="320" loading="lazy" class="h-full w-full object-cover">
            </{{ $tag }}>
        @endforeach
    </section>
@endif
