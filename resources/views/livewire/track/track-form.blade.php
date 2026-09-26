@php
    $statusLabel = [
        'pending' => 'Pending',
        'processing' => 'Confirmed',
        'shipped' => 'Shipped',
        'out_for_delivery' => 'Out for Delivery',
        'delivered' => 'Delivered',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];
    $happyPath = [
        ['key' => 'pending', 'label' => 'Pending'],
        ['key' => 'processing', 'label' => 'Confirmed'],
        ['key' => 'shipped', 'label' => 'Shipped'],
        ['key' => 'out_for_delivery', 'label' => 'Out for Delivery'],
        ['key' => 'delivered', 'label' => 'Delivered'],
    ];
    $currentIndex = $result ? collect($happyPath)->search(fn ($s) => $s['key'] === $result->status) : -1;
@endphp
<div class="max-w-xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold mb-1 text-center">Track Your Order</h1>
    <p class="text-gray-500 text-center mb-6">Phone Number দিয়ে (Order ID এর সাথে হলে অন্তত শেষ ৪ ডিজিট) অর্ডার status দেখুন</p>

    <form wire:submit="search" class="border border-gray-200 rounded-xl p-5 flex flex-col gap-3 mb-6">
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">Order ID / Invoice (ঐচ্ছিক)</span>
            <input type="text" wire:model="orderId" placeholder="e.g. 1234" class="border border-gray-300 rounded-lg px-3 py-2">
        </label>
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">Phone Number <span class="text-orange-500">*</span></span>
            <input type="text" wire:model="phone" placeholder="01XXXXXXXXX" class="border border-gray-300 rounded-lg px-3 py-2">
        </label>
        <p class="text-xs text-gray-400">Phone Number আবশ্যক (Order ID থাকলে সাথে শেষ ৪ ডিজিটই যথেষ্ট)</p>
        @if($error) <p class="text-red-600 text-sm">{{ $error }}</p> @endif
        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2.5 rounded-lg" wire:loading.attr="disabled" wire:target="search">
            <span wire:loading.remove wire:target="search">Track Order</span>
            <span wire:loading wire:target="search">খোঁজা হচ্ছে...</span>
        </button>
    </form>

    @if($result)
        <div class="border border-gray-200 rounded-xl p-5">
            <div class="flex justify-between items-center mb-4">
                <div class="font-semibold">Order #{{ $result->invoice_no ?? $result->id }}</div>
                <span class="text-xs font-semibold bg-orange-100 text-orange-600 px-2 py-1 rounded">{{ $statusLabel[$result->status] ?? $result->status }}</span>
            </div>

            @if($result->status === 'cancelled')
                <div class="flex items-center gap-3 bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-4">
                    <span class="h-8 w-8 rounded-full bg-red-500 text-white flex items-center justify-center shrink-0">✕</span>
                    <div>
                        <div class="font-semibold text-red-600">Cancelled</div>
                        <p class="text-xs text-red-500">এই অর্ডারটি বাতিল করা হয়েছে</p>
                    </div>
                </div>
            @else
                <div class="flex items-center mb-5">
                    @foreach($happyPath as $i => $step)
                        @php $reached = $i <= $currentIndex; @endphp
                        <div class="flex items-center flex-1 last:flex-none">
                            <div class="flex flex-col items-center gap-1">
                                <div class="h-8 w-8 rounded-full flex items-center justify-center text-sm font-semibold {{ $reached ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-400' }}">
                                    {{ $reached ? '✓' : $i + 1 }}
                                </div>
                                <span class="text-xs {{ $reached ? 'text-orange-500 font-medium' : 'text-gray-400' }}">{{ $step['label'] }}</span>
                            </div>
                            @if($i < count($happyPath) - 1)
                                <div class="flex-1 h-0.5 mx-2 {{ $i < $currentIndex ? 'bg-orange-500' : 'bg-gray-200' }}"></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex flex-col gap-2 mb-3">
                @foreach($result->items as $item)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ $item->product_name }} × {{ $item->quantity }}</span>
                        <span>@taka($item->line_total)</span>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between text-lg font-bold border-t border-gray-200 pt-3">
                <span>মোট</span>
                <span class="text-orange-500">@taka($result->total)</span>
            </div>
        </div>
    @elseif($searched && ! $error)
        <p class="text-center text-gray-400">কোনো ফলাফল পাওয়া যায়নি।</p>
    @endif
</div>
