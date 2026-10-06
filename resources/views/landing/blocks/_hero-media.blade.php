{{-- Hero image slider with an optional play button that opens the video in a modal.
     Params: $images, $video (VideoEmbed|null), $landingPage; optional $aspect, $wrapClass. --}}
@if ($images)
    <div x-data="{ video: false }">
        @include('landing.blocks._slider', [
            'images' => $images,
            'alt' => $landingPage->title,
            'aspect' => $aspect ?? 'aspect-square',
            'wrapClass' => $wrapClass ?? '',
            'slot' => $video ? new \Illuminate\Support\HtmlString(
                '<button type="button" @click="video = true" aria-label="ভিডিও চালান" class="absolute inset-0 m-auto h-16 w-16 rounded-full bg-white/90 text-brand-600 shadow-lg flex items-center justify-center text-2xl">▶</button>'
            ) : '',
        ])

        @if ($video)
            <div x-show="video" x-cloak x-transition.opacity
                 class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
                 @keydown.escape.window="video = false" @click.self="video = false">
                <div class="w-full max-w-3xl">
                    <button type="button" @click="video = false" class="mb-2 ml-auto block text-white text-2xl leading-none" aria-label="বন্ধ করুন">×</button>
                    <template x-if="video">
                        <div>@include('landing.blocks._video-player', ['video' => $video, 'title' => $landingPage->title])</div>
                    </template>
                </div>
            </div>
        @endif
    </div>
@endif
