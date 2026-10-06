<div class="grid md:grid-cols-3 gap-8">
    <form wire:submit="placeOrder" class="md:col-span-2 flex flex-col gap-4">
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">পূর্ণ নাম <span class="text-brand-500">*</span></span>
            <input type="text" wire:model="customer_name" class="border border-gray-300 rounded-lg px-3 py-2">
            @error('customer_name') <span class="text-error-500 text-xs">{{ $message }}</span> @enderror
        </label>
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">ফোন নম্বর <span class="text-brand-500">*</span></span>
            <input type="tel" wire:model="phone" placeholder="01XXXXXXXXX" class="border border-gray-300 rounded-lg px-3 py-2">
            @error('phone') <span class="text-error-500 text-xs">{{ $message }}</span> @enderror
        </label>
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">ইমেইল (ঐচ্ছিক)</span>
            <input type="email" wire:model="email" class="border border-gray-300 rounded-lg px-3 py-2">
            @error('email') <span class="text-error-500 text-xs">{{ $message }}</span> @enderror
        </label>
        <div class="grid grid-cols-2 gap-4">
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700">জেলা <span class="text-brand-500">*</span></span>
                <select wire:model="district" class="border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">নির্বাচন করুন</option>
                    @foreach($districts as $d)
                        <option value="{{ $d }}">{{ $d }}</option>
                    @endforeach
                </select>
                @error('district') <span class="text-error-500 text-xs">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700">থানা <span class="text-brand-500">*</span></span>
                <input type="text" wire:model="thana" class="border border-gray-300 rounded-lg px-3 py-2">
                @error('thana') <span class="text-error-500 text-xs">{{ $message }}</span> @enderror
            </label>
        </div>
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">পোস্ট কোড (ঐচ্ছিক)</span>
            <input type="text" wire:model="postcode" class="border border-gray-300 rounded-lg px-3 py-2">
        </label>
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">বিস্তারিত ঠিকানা <span class="text-brand-500">*</span></span>
            <textarea rows="3" wire:model="address" class="border border-gray-300 rounded-lg px-3 py-2"></textarea>
            @error('address') <span class="text-error-500 text-xs">{{ $message }}</span> @enderror
        </label>
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">অর্ডার নোট (ঐচ্ছিক)</span>
            <textarea rows="2" wire:model="notes" class="border border-gray-300 rounded-lg px-3 py-2"></textarea>
        </label>

        <div class="border border-gray-200 rounded-lg p-4">
            <div class="font-medium mb-3">পেমেন্ট পদ্ধতি</div>
            <div class="flex flex-col gap-3">
                <label class="flex items-start gap-3 p-3 rounded border cursor-pointer {{ $payment_method === 'cod' ? 'border-brand-500 bg-brand-50' : 'border-gray-200' }}">
                    <input type="radio" wire:model.live="payment_method" value="cod" class="mt-1">
                    <div>
                        <div class="font-medium">ক্যাশ অন ডেলিভারি (Cash on Delivery)</div>
                        <div class="text-xs text-gray-500">পণ্য হাতে পেয়ে ডেলিভারি ম্যানকে পেমেন্ট করুন</div>
                    </div>
                </label>
                @if ($bkashAvailable)
                    <label class="flex items-start gap-3 p-3 rounded border cursor-pointer {{ $payment_method === 'bkash' ? 'border-brand-500 bg-brand-50' : 'border-gray-200' }}">
                        <input type="radio" wire:model.live="payment_method" value="bkash" class="mt-1">
                        <div>
                            <div class="font-medium">বিকাশ (bKash)</div>
                            <div class="text-xs text-gray-500">অর্ডার করলে বিকাশের পেমেন্ট পেজে নিয়ে যাওয়া হবে, সেখানে পেমেন্ট সম্পন্ন করুন</div>
                        </div>
                    </label>
                @else
                    <label class="flex items-start gap-3 p-3 rounded border border-gray-200 opacity-50 cursor-not-allowed">
                        <input type="radio" disabled class="mt-1">
                        <div>
                            <div class="font-medium">বিকাশ (bKash)</div>
                            <div class="text-xs text-gray-500">শীঘ্রই আসছে</div>
                        </div>
                    </label>
                @endif
                @error('payment_method') <span class="text-error-500 text-xs">{{ $message }}</span> @enderror
                <label class="flex items-start gap-3 p-3 rounded border border-gray-200 opacity-50 cursor-not-allowed">
                    <input type="radio" disabled class="mt-1">
                    <div>
                        <div class="font-medium">কার্ড / মোবাইল ব্যাংকিং (SSLCommerz)</div>
                        <div class="text-xs text-gray-500">শীঘ্রই আসছে</div>
                    </div>
                </label>
            </div>
        </div>

        <div class="border border-gray-200 rounded-lg p-4">
            <div class="font-medium mb-2">কুপন কোড (ঐচ্ছিক)</div>
            <div class="flex gap-2">
                <input type="text" wire:model="couponCode" placeholder="কুপন কোড দিন" class="flex-1 border border-gray-300 rounded-lg px-3 py-2" @disabled($appliedCoupon)>
                @if(! $appliedCoupon)
                    <button type="button" wire:click="applyCoupon" class="bg-secondary text-white px-4 rounded-lg text-sm font-medium hover:bg-secondary/80">প্রয়োগ করুন</button>
                @else
                    <button type="button" wire:click="removeCoupon" class="bg-error-500 text-white px-4 rounded-lg text-sm font-medium hover:bg-error-600">বাতিল</button>
                @endif
            </div>
            @if($couponError) <p class="text-error-500 text-sm mt-2">{{ $couponError }}</p> @endif
            @if($appliedCoupon) <p class="text-success-600 text-sm mt-2">কুপন সফলভাবে প্রয়োগ করা হয়েছে!</p> @endif
        </div>

        @if($error) <p class="text-error-600 text-sm">{{ $error }}</p> @endif

        <button type="submit" class="bg-brand-500 hover:bg-brand-600 text-white font-semibold py-3 rounded-lg" wire:loading.attr="disabled" wire:target="placeOrder">
            <span wire:loading.remove wire:target="placeOrder">অর্ডার করুন</span>
            <span wire:loading wire:target="placeOrder">অর্ডার হচ্ছে...</span>
        </button>
    </form>

    <div class="border border-gray-200 rounded-xl p-5 h-fit">
        <div class="font-semibold mb-3">অর্ডার সামারি</div>
        <div class="flex flex-col gap-2 mb-3 max-h-64 overflow-y-auto">
            @foreach($lines as $line)
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">{{ $line['name'] }} × {{ $line['quantity'] }}</span>
                    <span>@taka($line['line_total'])</span>
                </div>
            @endforeach
        </div>
        <div class="flex justify-between text-sm mb-2">
            <span class="text-gray-600">সাবটোটাল</span>
            <span class="font-semibold">@taka($subtotal)</span>
        </div>
        <div class="flex justify-between text-sm mb-2">
            <span class="text-gray-600">ডেলিভারি চার্জ</span>
            <span class="font-semibold">@taka($shippingFee)</span>
        </div>
        <div class="flex justify-between text-sm mb-2">
            <span class="text-gray-600">ডিসকাউন্ট</span>
            <span class="font-semibold text-success-600">-@taka($appliedCoupon['discount'] ?? 0)</span>
        </div>
        <div class="flex justify-between text-lg font-bold border-t border-gray-200 pt-3">
            <span>মোট</span>
            <span class="text-brand-500">@taka(max(0, $subtotal + $shippingFee - ($appliedCoupon['discount'] ?? 0)))</span>
        </div>
    </div>
</div>
