@php
    $steps = ['pending' => 'অর্ডার গ্রহণ', 'processing' => 'কনফার্মড', 'shipped' => 'শিপড', 'out_for_delivery' => 'ডেলিভারির পথে', 'delivered' => 'ডেলিভারড'];
    $keys = array_keys($steps);
    $current = $result ? match ($result->status) {
        'completed' => count($keys) - 1,
        'on_hold' => 0,
        default => array_search($result->status, $keys, true),
    } : false;
    $input = 'block h-12 w-full rounded-xl border border-gray-300 bg-white px-4 text-base placeholder:text-gray-400 focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100';
@endphp
<div class="max-w-xl mx-auto px-4 py-6 md:py-10">
    <div class="mb-5 text-center">
        <h1 class="text-xl font-bold text-gray-900 md:text-2xl">অর্ডার ট্র্যাক করুন</h1>
        <p class="mt-1 text-sm text-gray-500">অর্ডার করার সময় দেওয়া মোবাইল নম্বর দিয়ে অর্ডারের অবস্থা দেখুন।</p>
    </div>

    <form wire:submit="search" class="mb-6 space-y-4 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5">
        <div>
            <label for="tr-phone" class="mb-1.5 block text-sm font-medium text-gray-700">মোবাইল নম্বর <span class="text-error-500">*</span></label>
            <input id="tr-phone" type="tel" wire:model="phone" inputmode="numeric" autocomplete="tel" maxlength="14" placeholder="01XXXXXXXXX" class="{{ $input }}">
        </div>
        <div>
            <label for="tr-order" class="mb-1.5 block text-sm font-medium text-gray-700">অর্ডার নম্বর <span class="font-normal text-gray-400">(ঐচ্ছিক)</span></label>
            <input id="tr-order" type="text" wire:model="orderId" autocomplete="off" placeholder="যেমন: INV-261006-ABC123" class="{{ $input }} uppercase">
            <p class="mt-1.5 text-xs text-gray-500">অর্ডার নম্বর দিলে ফোনের শেষ ৪ ডিজিট দিলেই হবে।</p>
        </div>
        @if($error)
            <p class="rounded-xl bg-error-50 px-4 py-3 text-sm font-medium text-error-500" role="alert">{{ $error }}</p>
        @endif
        <button type="submit" class="inline-flex h-12 w-full items-center justify-center rounded-xl bg-brand-500 font-semibold text-white hover:bg-brand-600 disabled:opacity-70" wire:loading.attr="disabled" wire:target="search">
            <span wire:loading.remove wire:target="search">অর্ডার খুঁজুন</span>
            <span wire:loading wire:target="search">খোঁজা হচ্ছে...</span>
        </button>
    </form>

    @if($result)
        <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5" aria-label="অর্ডারের অবস্থা">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p class="font-semibold text-gray-900">{{ $result->invoice_no ?? '#'.$result->id }}</p>
                    <p class="text-xs text-gray-500">{{ $result->created_at?->locale('bn')->translatedFormat('j F Y, g:i A') }}</p>
                </div>
                <span @class([
                    'rounded-full px-3 py-1 text-xs font-semibold',
                    'bg-error-50 text-error-500' => $result->status === 'cancelled',
                    'bg-success-50 text-success-700' => in_array($result->status, ['delivered', 'completed'], true),
                    'bg-brand-50 text-brand-600' => ! in_array($result->status, ['cancelled', 'delivered', 'completed'], true),
                ])>{{ \App\Support\OrderStatus::label($result->status) }}</span>
            </div>

            @if($result->status === 'cancelled')
                <p class="mb-4 rounded-xl bg-error-50 px-4 py-3 text-sm text-error-500">এই অর্ডারটি বাতিল করা হয়েছে। কোনো প্রশ্ন থাকলে আমাদের কল করুন।</p>
            @else
                @if($result->status === 'on_hold')
                    <p class="mb-4 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-600">আপনার অর্ডারটি যাচাই করা হচ্ছে — আমরা শিগগিরই আপনার সাথে যোগাযোগ করব।</p>
                @endif
                {{-- Vertical timeline: fits any phone width --}}
                <ol class="mb-5">
                    @foreach($steps as $key => $label)
                        @php $i = $loop->index; $done = $current !== false && $i <= $current; $now = $current === $i; @endphp
                        <li class="relative flex gap-3 pb-4 last:pb-0">
                            @unless($loop->last)
                                <span class="absolute left-[15px] top-8 h-[calc(100%-2rem)] w-0.5 {{ $current !== false && $i < $current ? 'bg-brand-500' : 'bg-gray-200' }}" aria-hidden="true"></span>
                            @endunless
                            <span @class([
                                'relative z-10 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                                'bg-brand-500 text-white' => $done,
                                'bg-gray-100 text-gray-400' => ! $done,
                                'ring-4 ring-brand-100' => $now,
                            ])>{{ $done ? '✓' : $i + 1 }}</span>
                            <span @class(['pt-1.5 text-sm', 'font-semibold text-gray-900' => $now, 'text-gray-700' => $done && ! $now, 'text-gray-400' => ! $done])>
                                {{ $label }}@if($now) <span class="font-normal text-brand-600">— এখন এখানে</span>@endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif

            <ul class="space-y-2 border-t border-gray-100 pt-4 text-sm">
                @foreach($result->items as $item)
                    <li class="flex justify-between gap-3"><span class="min-w-0 text-gray-600">{{ $item->product_name }} × {{ $item->quantity }}</span><span class="shrink-0 tabular-nums">@taka($item->line_total)</span></li>
                @endforeach
            </ul>
            <div class="mt-3 flex justify-between border-t border-gray-100 pt-3 text-lg font-bold">
                <span>মোট</span><span class="text-brand-600 tabular-nums">@taka($result->total)</span>
            </div>

            <a href="{{ route('order.invoice', $result->order_token) }}" target="_blank" rel="noopener"
               class="mt-4 inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border-2 border-gray-300 text-sm font-semibold text-gray-700 hover:border-gray-400 sm:w-auto sm:px-5">
                ইনভয়েস দেখুন / ডাউনলোড
            </a>
        </section>
    @elseif($searched && ! $error)
        <p class="text-center text-gray-500">কোনো অর্ডার পাওয়া যায়নি।</p>
    @endif
</div>
