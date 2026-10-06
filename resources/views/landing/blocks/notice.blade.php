{{-- Notice ("গুরুত্বপূর্ণ বিষয়") — dark band, centered heading, ✓ lines separated by thin rules. --}}
@php
    $items = array_values(array_filter(array_map(fn ($b) => is_array($b) ? ($b['text'] ?? null) : $b, (array) ($d['items'] ?? []))));
@endphp
@if ($items)
    <section class="bg-brand-600 px-4 py-7 text-white">
        <div class="max-w-4xl mx-auto text-center">
            @if (! empty($d['heading']))
                <h2 class="mb-3 text-lg md:text-2xl font-extrabold">{{ $d['heading'] }}</h2>
            @endif
            <ul class="divide-y divide-white/25 border-t border-white/25 text-sm md:text-base">
                @foreach ($items as $line)
                    <li class="flex items-start justify-center gap-2 py-2.5"><span aria-hidden="true">✓</span><span>{{ $line }}</span></li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
