@php
    $items = array_values(array_filter(array_map(fn ($b) => is_array($b) ? ($b['text'] ?? null) : $b, (array) ($d['items'] ?? []))));
@endphp
@if ($items)
    <section class="bg-brand-600 px-4 py-7 text-white">
        <div class="max-w-3xl mx-auto">
            @if (! empty($d['heading']))
                <h2 class="text-center text-lg md:text-2xl font-extrabold mb-3">{{ $d['heading'] }}</h2>
            @endif
            <ul class="divide-y divide-white/20 text-sm md:text-base">
                @foreach ($items as $line)
                    <li class="flex gap-2 py-2"><span aria-hidden="true">✓</span><span>{{ $line }}</span></li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
