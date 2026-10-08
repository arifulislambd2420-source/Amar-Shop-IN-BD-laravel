<span>
@if($count > 0)
    <span @class([
        'absolute text-white font-bold rounded-full flex items-center justify-center',
        'text-[10px] h-4 w-4' => ! $nav,
        '-top-1 -right-1 bg-white text-brand-500 h-5 w-5 border border-brand-500' => $floating,
        '-top-2 -right-2 bg-brand-500' => ! $floating && ! $nav,
        'top-0 right-2 h-5 min-w-5 px-1 text-[11px] bg-error-500 ring-2 ring-white' => $nav,
    ]) @if($nav) aria-label="কার্টে {{ $count }}টি পণ্য" @endif>{{ $count > 9 ? '9+' : $count }}</span>
@endif
</span>
