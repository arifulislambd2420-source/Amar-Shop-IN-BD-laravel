<span>
@if($count > 0)
    <span @class([
        'absolute text-white text-[10px] font-bold rounded-full h-4 w-4 flex items-center justify-center',
        '-top-1 -right-1 bg-white text-orange-500 h-5 w-5 border border-orange-500' => $floating,
        '-top-2 -right-2 bg-orange-500' => ! $floating,
    ])>{{ $count > 9 ? '9+' : $count }}</span>
@endif
</span>
