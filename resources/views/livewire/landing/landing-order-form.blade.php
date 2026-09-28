<div class="w-full max-w-md mx-auto bg-white rounded-2xl shadow-xl p-6 text-gray-800" id="order-form">
    <h3 class="text-lg font-bold text-center mb-4">এখনই অর্ডার করুন</h3>

    @if ($error)
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-600 text-sm px-3 py-2">
            {{ $error }}
        </div>
    @endif

    <form wire:submit="placeOrder" class="flex flex-col gap-3">
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">পূর্ণ নাম <span class="text-orange-500">*</span></span>
            <input type="text" wire:model="customer_name"
                   class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-400">
            @error('customer_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </label>

        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">ফোন নম্বর <span class="text-orange-500">*</span></span>
            <input type="tel" wire:model="phone" placeholder="01XXXXXXXXX"
                   class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-400">
            @error('phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </label>

        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">বিস্তারিত ঠিকানা <span class="text-orange-500">*</span></span>
            <textarea rows="2" wire:model="address"
                      class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-400"></textarea>
            @error('address') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </label>

        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">পরিমাণ</span>
            <input type="number" min="1" max="20" wire:model="quantity"
                   class="border border-gray-300 rounded-lg px-3 py-2 w-24 focus:outline-none focus:ring-2 focus:ring-orange-400">
            @error('quantity') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </label>

        <button type="submit"
                wire:loading.attr="disabled"
                wire:target="placeOrder"
                class="mt-2 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-bold rounded-lg px-4 py-3 text-center transition">
            <span wire:loading.remove wire:target="placeOrder">{{ $buttonText }}</span>
            <span wire:loading wire:target="placeOrder">প্রসেস হচ্ছে…</span>
        </button>

        <p class="text-center text-xs text-gray-400 mt-1">ক্যাশ অন ডেলিভারি — পণ্য হাতে পেয়ে টাকা দিন</p>
    </form>
</div>
