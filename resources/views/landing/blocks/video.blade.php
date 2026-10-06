@php($video = \App\Support\VideoEmbed::parse($d['url'] ?? null))
@if ($video)
    <section class="bg-white px-4 py-8 md:py-10">
        <div class="max-w-3xl mx-auto">
            @if (! empty($d['heading']))
                <h2 class="text-center text-xl md:text-3xl font-extrabold text-ink mb-5">{{ $d['heading'] }}</h2>
            @endif
            @include('landing.blocks._video-player', ['video' => $video, 'title' => $d['heading'] ?? $landingPage->title, 'poster' => $d['poster'] ?? null])
        </div>
    </section>
@endif
