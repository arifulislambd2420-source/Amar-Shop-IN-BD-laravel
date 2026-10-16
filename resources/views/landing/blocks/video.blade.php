@php($video = \App\Support\VideoEmbed::parse($d['url'] ?? null))
@if ($video)
    {{-- On a phone the video runs edge to edge (no side padding, square corners); from md up it sits in a rounded box. --}}
    <section class="bg-white px-0 py-8 md:px-4 md:py-10">
        <div class="max-w-3xl mx-auto">
            @if (! empty($d['heading']))
                <h2 class="px-4 text-center text-xl md:text-3xl font-extrabold text-ink mb-5 md:px-0">{{ $d['heading'] }}</h2>
            @endif
            @include('landing.blocks._video-player', ['video' => $video, 'title' => $d['heading'] ?? $landingPage->title, 'poster' => $d['poster'] ?? null, 'class' => 'rounded-none md:rounded-xl'])
        </div>
    </section>
@endif
