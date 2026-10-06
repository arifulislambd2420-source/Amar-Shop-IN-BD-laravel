{{-- Renders a parsed VideoEmbed ($video) at full width, 16:9. --}}
@if ($video['type'] === 'iframe')
    <iframe src="{{ $video['src'] }}" class="w-full aspect-video rounded-xl" loading="lazy"
            allow="accelerometer; autoplay; encrypted-media; picture-in-picture" allowfullscreen
            title="{{ $title ?? 'Video' }}"></iframe>
@else
    <video src="{{ $video['src'] }}" class="w-full aspect-video rounded-xl bg-black" controls playsinline preload="metadata"
           @if (! empty($poster)) poster="{{ $poster }}" @endif></video>
@endif
