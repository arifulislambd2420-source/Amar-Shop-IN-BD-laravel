{{-- Category tile: the picture fills the square (object-cover), the name sits under it.
     No picture → a soft gradient with the emoji (or a bag) as placeholder.
     Props: category, href, active (highlight, e.g. the one being shown). --}}
@props(['category', 'href', 'active' => false])
@php
    $icon = $category->icon;
    $hasImage = \App\Filament\Resources\Categories\CategoryResource::isImageIcon($icon);
@endphp
<a href="{{ $href }}" @if($active) aria-current="page" @endif
   {{ $attributes->class([
       'group flex flex-col overflow-hidden rounded-2xl border bg-white transition-colors hover:border-brand-500',
       'border-brand-500 ring-2 ring-brand-500/30' => $active,
       'border-gray-200' => ! $active,
   ]) }}>
    <span class="relative block aspect-square w-full overflow-hidden bg-gradient-to-br from-brand-50 to-brand-100">
        @if($hasImage)
            <img src="{{ $icon }}" alt="" width="240" height="240" loading="lazy"
                 class="absolute inset-0 h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
        @else
            <span class="absolute inset-0 flex items-center justify-center text-4xl leading-none sm:text-5xl" aria-hidden="true">{{ filled($icon) ? $icon : '🛍️' }}</span>
        @endif
    </span>
    <span class="flex min-h-10 items-center justify-center px-2 py-1.5 text-center text-xs font-medium leading-tight text-gray-800 group-hover:text-brand-600 sm:text-sm">
        <span class="line-clamp-2">{{ $category->name }}</span>
    </span>
</a>
