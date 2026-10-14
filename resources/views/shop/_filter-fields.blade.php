{{-- The shop's filter checkboxes — one set, used in the desktop sidebar and in the phone sheet.
     Expects: $filters (ShopFilters::fromRequest), $options (ShopFilters::options),
     $categories, $brands, $idp (id prefix, so the two copies don't clash). --}}
@php
    $group = 'group border-b border-gray-100 pb-3 last:border-0';
    $summary = 'flex min-h-11 cursor-pointer list-none items-center justify-between text-sm font-semibold text-gray-900 [&::-webkit-details-marker]:hidden';
    $row = 'flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-2 hover:bg-gray-50 md:min-h-9';
    $box = 'h-5 w-5 shrink-0 rounded border-gray-300 accent-brand-500';
    $chevron = '<svg class="shrink-0 text-gray-400 transition-transform group-open:rotate-180" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
@endphp

<div class="space-y-1">
    @if ($categories->isNotEmpty())
        <details class="{{ $group }}" open>
            <summary class="{{ $summary }}">ক্যাটাগরি {!! $chevron !!}</summary>
            <div class="max-h-60 overflow-y-auto">
                @foreach ($categories as $c)
                    <label class="{{ $row }}">
                        <input type="checkbox" name="category[]" value="{{ $c->slug }}" class="{{ $box }}" @checked(in_array($c->slug, $filters['category'], true))>
                        <span class="min-w-0 truncate text-sm text-gray-700">{{ $c->name }}</span>
                    </label>
                @endforeach
            </div>
        </details>
    @endif

    @if ($brands->isNotEmpty())
        <details class="{{ $group }}" @if ($filters['brand']) open @endif>
            <summary class="{{ $summary }}">ব্র্যান্ড {!! $chevron !!}</summary>
            <div class="max-h-60 overflow-y-auto">
                @foreach ($brands as $b)
                    <label class="{{ $row }}">
                        <input type="checkbox" name="brand[]" value="{{ $b->id }}" class="{{ $box }}" @checked(in_array((string) $b->id, $filters['brand'], true))>
                        <span class="min-w-0 truncate text-sm text-gray-700">{{ $b->name }}</span>
                    </label>
                @endforeach
            </div>
        </details>
    @endif

    @if ($options['colors']->isNotEmpty())
        <details class="{{ $group }}" open>
            <summary class="{{ $summary }}">রং {!! $chevron !!}</summary>
            <div class="max-h-60 overflow-y-auto">
                @foreach ($options['colors'] as $color)
                    <label class="{{ $row }}">
                        <input type="checkbox" name="color[]" value="{{ $color->color }}" class="{{ $box }}" @checked(in_array($color->color, $filters['color'], true))>
                        @if ($color->hex)
                            <span class="h-4 w-4 shrink-0 rounded-full border border-black/10" style="background: {{ $color->hex }}" aria-hidden="true"></span>
                        @endif
                        <span class="min-w-0 truncate text-sm text-gray-700">{{ $color->color }}</span>
                    </label>
                @endforeach
            </div>
        </details>
    @endif

    @if ($options['sizes'])
        <details class="{{ $group }}" open>
            <summary class="{{ $summary }}">সাইজ {!! $chevron !!}</summary>
            <div class="flex flex-wrap gap-2 pt-1">
                @foreach ($options['sizes'] as $size)
                    <label class="cursor-pointer">
                        <input type="checkbox" name="size[]" value="{{ $size }}" class="peer sr-only" @checked(in_array($size, $filters['size'], true))>
                        <span class="inline-flex h-11 min-w-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 transition peer-checked:border-brand-500 peer-checked:bg-brand-500 peer-checked:text-white peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-500 md:h-9">{{ $size }}</span>
                    </label>
                @endforeach
            </div>
        </details>
    @endif

    @if ($options['price']['max'] > 0)
        <details class="{{ $group }}" open>
            <summary class="{{ $summary }}">দাম (৳) {!! $chevron !!}</summary>
            <div class="flex items-center gap-2 pt-1">
                <label class="min-w-0 flex-1">
                    <span class="sr-only">সর্বনিম্ন দাম</span>
                    <input type="number" inputmode="numeric" min="0" name="price_min" value="{{ $filters['price_min'] }}" placeholder="{{ $options['price']['min'] }}"
                        class="h-11 w-full rounded-xl border border-gray-300 bg-white px-3 text-base md:h-10 md:text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                </label>
                <span class="text-gray-400" aria-hidden="true">–</span>
                <label class="min-w-0 flex-1">
                    <span class="sr-only">সর্বোচ্চ দাম</span>
                    <input type="number" inputmode="numeric" min="0" name="price_max" value="{{ $filters['price_max'] }}" placeholder="{{ $options['price']['max'] }}"
                        class="h-11 w-full rounded-xl border border-gray-300 bg-white px-3 text-base md:h-10 md:text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                </label>
            </div>
        </details>
    @endif

    <div class="pt-2">
        <label class="{{ $row }}">
            <input type="checkbox" name="in_stock" value="1" class="{{ $box }}" @checked($filters['in_stock'])>
            <span class="text-sm font-medium text-gray-700">শুধু স্টকে আছে</span>
        </label>
    </div>
</div>
