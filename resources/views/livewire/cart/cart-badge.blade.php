<span>
@if($count > 0)
    <span @class([
        'absolute text-white font-bold rounded-full flex items-center justify-center',
        'text-[10px] h-4 w-4' => ! $nav,
        '-top-1 -right-1 bg-white text-brand-500 h-5 w-5 border border-brand-500' => $floating,
        '-top-2 -right-2 bg-brand-500' => ! $floating && ! $nav,
        '-top-1.5 -right-2.5 h-[18px] min-w-[18px] px-1 text-[10px] bg-brand-500 ring-2 ring-[var(--mnav-bg)]' => $nav,
    ]) @if($nav) aria-label="কার্টে {{ $count }}টি পণ্য" @endif>{{ $count > 9 ? '9+' : $count }}</span>
@endif
</span>
