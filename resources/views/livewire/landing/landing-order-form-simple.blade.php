<div class="w-full mx-auto bg-white text-ink {{ $packages ? '' : 'max-w-md rounded-2xl shadow-xl p-6' }}" id="{{ $rootId }}">
    @if ($heading)
        <h3 class="text-lg font-bold text-center mb-4">{{ $heading }}</h3>
    @endif

    @if ($error)
        <div class="mb-4 rounded-lg bg-error-50 border border-error-200 text-error-600 text-sm px-3 py-2">
            {{ $error }}
        </div>
    @endif

    <form wire:submit="placeOrder" class="flex flex-col gap-3">
        {{-- Packages (block-builder pages): the page's own price list. --}}
        @if ($packages)
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($packages as $i => $pkg)
                    <label class="relative flex cursor-pointer items-center gap-3 rounded-xl border-2 p-3 transition
                                  {{ $packageIndex === $i ? 'border-brand-500 bg-brand-50' : 'border-gray-200 bg-white' }}">
                        <input type="radio" wire:model.live="packageIndex" value="{{ $i }}" class="accent-brand-500">
                        @if (! empty($pkg['image']))
                            <img src="{{ $pkg['image'] }}" alt="" width="56" height="56" loading="lazy" class="h-12 w-12 shrink-0 rounded-lg object-cover">
                        @endif
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold leading-snug">{{ $pkg['label'] ?? '' }}</span>
                            <span class="block text-sm">
                                <span class="font-bold text-brand-600">@taka($pkg['price'])</span>
                                @if (! empty($pkg['compare_price']) && (float) $pkg['compare_price'] > (float) $pkg['price'])
                                    <span class="ml-1 text-xs text-gray-400 line-through">@taka($pkg['compare_price'])</span>
                                @endif
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('packageIndex') <span class="text-error-500 text-xs">{{ $message }}</span> @enderror
        @endif

        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">পূর্ণ নাম <span class="text-brand-500">*</span></span>
            <input type="text" wire:model="customer_name" autocomplete="name"
                   class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
            @error('customer_name') <span class="text-error-500 text-xs">{{ $message }}</span> @enderror
        </label>

        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">ফোন নম্বর <span class="text-brand-500">*</span></span>
            <input type="tel" wire:model.blur="phone" placeholder="01XXXXXXXXX" autocomplete="tel"
                   class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
            @error('phone') <span class="text-error-500 text-xs">{{ $message }}</span> @enderror
        </label>

        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">বিস্তারিত ঠিকানা <span class="text-brand-500">*</span></span>
            <textarea rows="2" wire:model="address" autocomplete="street-address"
                      class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400"></textarea>
            @error('address') <span class="text-error-500 text-xs">{{ $message }}</span> @enderror
        </label>

        @if (! empty($variants['sizes']) || ! empty($variants['colors']))
            <div class="grid gap-3 sm:grid-cols-2">
                @if (! empty($variants['sizes']))
                    <label class="flex flex-col gap-1 text-sm">
                        <span class="font-medium text-gray-700">{{ $variants['size_label'] }} @if ($variants['size_required'])<span class="text-brand-500">*</span>@endif</span>
                        <select wire:model="size" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
                            <option value="">নির্বাচন করুন</option>
                            @foreach ($variants['sizes'] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                        @error('size') <span class="text-error-500 text-xs">{{ $message }}</span> @enderror
                    </label>
                @endif
                @if (! empty($variants['colors']))
                    <label class="flex flex-col gap-1 text-sm">
                        <span class="font-medium text-gray-700">{{ $variants['color_label'] }} @if ($variants['color_required'])<span class="text-brand-500">*</span>@endif</span>
                        <select wire:model="color" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
                            <option value="">নির্বাচন করুন</option>
                            @foreach ($variants['colors'] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                        @error('color') <span class="text-error-500 text-xs">{{ $message }}</span> @enderror
                    </label>
                @endif
            </div>
        @endif

        @if (! $packages)
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700">পরিমাণ</span>
                <input type="number" min="1" max="20" wire:model.live="quantity"
                       class="border border-gray-300 rounded-lg px-3 py-2 w-24 focus:outline-none focus:ring-2 focus:ring-brand-400">
                @error('quantity') <span class="text-error-500 text-xs">{{ $message }}</span> @enderror
            </label>
        @endif

        {{-- Delivery zone: only when inside/outside Dhaka cost differently. --}}
        @if ($zoneChoice)
            <fieldset class="flex flex-col gap-2 text-sm">
                <legend class="mb-1 font-medium text-gray-700">ডেলিভারি এলাকা</legend>
                <label class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2">
                    <span class="flex items-center gap-2"><input type="radio" wire:model.live="zone" value="dhaka" class="accent-brand-500"> ঢাকার ভিতরে</span>
                    <span class="text-gray-600">@taka($dhakaFee)</span>
                </label>
                <label class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2">
                    <span class="flex items-center gap-2"><input type="radio" wire:model.live="zone" value="outside" class="accent-brand-500"> ঢাকার বাইরে</span>
                    <span class="text-gray-600">@taka($outsideFee)</span>
                </label>
            </fieldset>
        @endif

        @if ($showSummary)
            <div class="rounded-lg bg-gray-50 p-3 text-sm space-y-1">
                <div class="flex justify-between"><span class="text-gray-600">সাবটোটাল</span><span>@taka($subtotal)</span></div>
                <div class="flex justify-between">
                    <span class="text-gray-600">ডেলিভারি চার্জ</span>
                    @if ($shippingFee == 0)
                        <span class="font-semibold text-success-600">ফ্রি</span>
                    @else
                        <span>@taka($shippingFee)</span>
                    @endif
                </div>
                <div class="flex justify-between border-t border-gray-200 pt-1 font-bold"><span>মোট</span><span class="text-brand-600">@taka($subtotal + $shippingFee)</span></div>
            </div>
        @endif

        <button type="submit"
                wire:loading.attr="disabled"
                wire:target="placeOrder"
                class="mt-2 bg-brand-500 hover:bg-brand-600 disabled:opacity-60 text-white font-bold rounded-lg px-4 py-3 text-center transition">
            <span wire:loading.remove wire:target="placeOrder">{{ $buttonText }}@if ($showSummary) — @taka($subtotal + $shippingFee)@endif</span>
            <span wire:loading wire:target="placeOrder">প্রসেস হচ্ছে…</span>
        </button>

        @if ($note)
            <p class="text-center text-xs text-gray-500 whitespace-pre-line">{{ $note }}</p>
        @endif
        <p class="text-center text-xs text-gray-400 mt-1">ক্যাশ অন ডেলিভারি — পণ্য হাতে পেয়ে টাকা দিন</p>
    </form>
</div>
