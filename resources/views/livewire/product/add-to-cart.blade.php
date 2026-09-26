<div>
@if($product)
    @if($mode === 'card')
        <div class="flex items-center gap-2">
            <button type="button" wire:click="buyNow" @disabled($outOfStock)
                class="flex-1 rounded-lg text-sm font-semibold py-1.5 transition-colors {{ $outOfStock ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-orange-500 text-white hover:bg-orange-600' }}">
                {{ $outOfStock ? 'স্টক নেই' : 'অর্ডার করুন' }}
            </button>
            <button type="button" wire:click="add" @disabled($outOfStock) aria-label="কার্টে যোগ করুন"
                class="h-8 w-8 shrink-0 rounded-lg border border-gray-300 flex items-center justify-center hover:border-orange-500 hover:text-orange-500 disabled:opacity-40">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </button>
        </div>
    @else
        <div class="flex flex-col gap-3">
            @if($product->variants->isNotEmpty())
                <label class="flex flex-col gap-1 text-sm">
                    <span class="font-medium text-gray-700">ভ্যারিয়েন্ট নির্বাচন করুন</span>
                    <select wire:model.live="variantId" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        @foreach($product->variants as $v)
                            <option value="{{ $v->id }}" @disabled($v->stock <= 0)>
                                {{ $v->label }} — @taka($v->price) @if($v->stock <= 0) (স্টক নেই) @endif
                            </option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="flex flex-col gap-1 text-sm w-28">
                <span class="font-medium text-gray-700">পরিমাণ</span>
                <input type="number" min="1" wire:model="quantity" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </label>

            <div class="flex gap-3">
                <button type="button" wire:click="add" @disabled($outOfStock)
                    class="flex-1 rounded-lg font-semibold py-2.5 border border-orange-500 text-orange-500 hover:bg-orange-50 disabled:opacity-40 disabled:cursor-not-allowed">
                    কার্টে যোগ করুন
                </button>
                <button type="button" wire:click="buyNow" @disabled($outOfStock)
                    class="flex-1 rounded-lg font-semibold py-2.5 bg-orange-500 text-white hover:bg-orange-600 disabled:opacity-40 disabled:cursor-not-allowed">
                    {{ $outOfStock ? 'স্টক নেই' : 'এখনই অর্ডার করুন' }}
                </button>
            </div>

            @if($message)
                <p class="text-green-600 text-sm">{{ $message }}</p>
            @endif
        </div>
    @endif
@endif
</div>
