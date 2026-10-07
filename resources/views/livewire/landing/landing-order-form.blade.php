{{-- Landing order form for block-builder templates (green / purple / cream).
     One column on mobile; on md+ the left column has products + billing and
     the right column the order summary, payment and confirm button. Colours
     come from the page wrapper's --brand / --secondary. --}}
@php
    $total = $subtotal + $shippingFee;
    $inputClass = 'w-full rounded-md border border-brand-200 bg-secondary/10 px-3 py-2.5 text-sm text-ink focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-400/40';
    $label = fn (string $zone) => $zone === 'dhaka' ? $dhakaFee : $outsideFee;
@endphp

<div id="{{ $rootId }}" class="w-full text-ink">
    @if ($heading)
        <h3 class="mb-4 text-center text-lg font-bold">{{ $heading }}</h3>
    @endif

    @if ($error)
        <div class="mb-4 rounded-lg border border-error-200 bg-error-50 px-3 py-2 text-sm text-error-600">{{ $error }}</div>
    @endif

    <form wire:submit="placeOrder" class="grid gap-6 md:grid-cols-2 md:gap-8">
        {{-- ───────── Left: products + billing + delivery ───────── --}}
        <div class="space-y-6">
            <section>
                <h4 class="mb-2 text-sm font-bold text-ink">প্রোডাক্ট সিলেক্ট করুন</h4>
                <div class="space-y-2.5">
                    @foreach ($cards as $i => $c)
                        @php($selected = $packageIndex === $i)
                        <div wire:key="pkg-{{ $i }}" wire:click="selectPackage({{ $i }})"
                             class="relative flex cursor-pointer items-center gap-3 overflow-hidden rounded-lg border-2 p-3 transition
                                    {{ $selected ? 'border-brand-500 bg-brand-50' : 'border-gray-200 bg-white hover:border-brand-200' }}">
                            @if ($c['compare_price'] && $c['compare_price'] > $c['price'])
                                <span class="absolute right-0 top-0 rounded-bl-lg bg-brand-600 px-2.5 py-0.5 text-[10px] font-bold text-white">অফার</span>
                            @endif

                            <input type="radio" name="package" value="{{ $i }}" @checked($selected) class="accent-brand-500"
                                   aria-label="{{ $c['label'] }}" tabindex="-1">

                            @if (! empty($c['image']))
                                <img src="{{ $c['image'] }}" alt="" width="56" height="56" loading="lazy" class="h-14 w-14 shrink-0 rounded-md object-cover">
                            @endif

                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold leading-snug">{{ $c['label'] }}</p>
                                <p class="mt-0.5 text-sm">
                                    <span class="font-bold text-brand-600">@taka($c['price'])</span>
                                    @if ($c['compare_price'] && $c['compare_price'] > $c['price'])
                                        <span class="ml-1 text-xs text-gray-400 line-through">@taka($c['compare_price'])</span>
                                    @endif
                                </p>
                            </div>

                            @if ($selected)
                                <div class="flex shrink-0 items-center overflow-hidden rounded-md border border-brand-200 bg-white" wire:click.stop>
                                    <button type="button" wire:click="decrement" aria-label="কমান" class="h-8 w-8 text-lg leading-none text-brand-600 hover:bg-secondary/20">−</button>
                                    <span class="w-7 text-center text-sm font-semibold">{{ $quantity }}</span>
                                    <button type="button" wire:click="increment" aria-label="বাড়ান" class="h-8 w-8 text-lg leading-none text-brand-600 hover:bg-secondary/20">+</button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="space-y-3">
                <h4 class="text-sm font-bold text-ink">আপনার তথ্য দিন</h4>

                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-gray-700">আপনার নাম <span class="text-error-500">*</span></span>
                    <input type="text" wire:model="customer_name" autocomplete="name" class="{{ $inputClass }}">
                    @error('customer_name') <span class="text-xs text-error-500">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-gray-700">ডেলিভারি ঠিকানা <span class="text-error-500">*</span></span>
                    <textarea rows="2" wire:model="address" autocomplete="street-address" placeholder="গ্রাম / থানা / জেলা" class="{{ $inputClass }}"></textarea>
                    @error('address') <span class="text-xs text-error-500">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-gray-700">ফোন নম্বর <span class="text-error-500">*</span></span>
                    <input type="tel" wire:model.blur="phone" placeholder="01XXXXXXXXX" autocomplete="tel" class="{{ $inputClass }}">
                    @error('phone') <span class="text-xs text-error-500">{{ $message }}</span> @enderror
                </label>

                @if ($variants['size_enabled'] || $variants['color_enabled'])
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach (['size', 'color'] as $field)
                            @if ($variants[$field.'_enabled'])
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-gray-700">{{ $variants[$field.'_label'] }} @if ($variants[$field.'_required'])<span class="text-error-500">*</span>@endif</span>
                                    @if ($variants[$field.'_options'])
                                        <select wire:model="{{ $field }}" class="{{ $inputClass }}">
                                            <option value="">নির্বাচন করুন</option>
                                            @foreach ($variants[$field.'_options'] as $option)
                                                <option value="{{ $option }}">{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" wire:model="{{ $field }}" maxlength="100" class="{{ $inputClass }}">
                                    @endif
                                    @error($field) <span class="text-xs text-error-500">{{ $message }}</span> @enderror
                                </label>
                            @endif
                        @endforeach
                    </div>
                @endif
            </section>

            <section>
                <h4 class="mb-2 text-sm font-bold text-ink">ডেলিভারি</h4>
                <div class="space-y-2 text-sm">
                    @foreach (['dhaka' => 'ঢাকার ভিতরে', 'outside' => 'ঢাকার বাইরে'] as $z => $zoneLabel)
                        <label class="flex cursor-pointer items-center justify-between rounded-md border px-3 py-2.5
                                      {{ $zone === $z ? 'border-brand-500 bg-brand-50' : 'border-gray-200' }}">
                            <span class="flex items-center gap-2">
                                <input type="radio" wire:model.live="zone" value="{{ $z }}" class="accent-brand-500"> {{ $zoneLabel }}
                            </span>
                            <span class="text-gray-600">
                                @if ($label($z) == 0) <span class="font-semibold text-success-600">ফ্রি</span> @else @taka($label($z)) @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- ───────── Right: summary + payment + confirm ───────── --}}
        <div class="space-y-5 md:sticky md:top-4 md:self-start">
            <section class="rounded-lg border border-gray-200 bg-white p-4">
                <h4 class="mb-3 text-sm font-bold text-ink">আপনার অর্ডার</h4>

                @if ($card)
                    <div class="flex items-center gap-3 border-b border-gray-100 pb-3">
                        @if (! empty($card['image']))
                            <img src="{{ $card['image'] }}" alt="" width="48" height="48" loading="lazy" class="h-12 w-12 shrink-0 rounded-md object-cover">
                        @endif
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="font-medium leading-snug">{{ $card['label'] }}</p>
                            <p class="text-xs text-gray-500">× {{ $quantity }}</p>
                        </div>
                        <span class="shrink-0 text-sm font-semibold">@taka($subtotal)</span>
                    </div>
                @endif

                <dl class="mt-3 space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-600">সাবটোটাল</dt><dd>@taka($subtotal)</dd></div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">ডেলিভারি চার্জ</dt>
                        <dd>@if ($shippingFee == 0)<span class="font-semibold text-success-600">ফ্রি</span>@else @taka($shippingFee) @endif</dd>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-bold">
                        <dt>মোট</dt><dd class="text-brand-600">@taka($total)</dd>
                    </div>
                </dl>
            </section>

            <section class="space-y-2 text-sm">
                <label class="flex cursor-pointer items-start gap-2 rounded-md border px-3 py-2.5
                              {{ $payment_method === 'cod' ? 'border-brand-500 bg-brand-50' : 'border-gray-200' }}">
                    <input type="radio" wire:model.live="payment_method" value="cod" class="mt-1 accent-brand-500">
                    <span>
                        <span class="block font-semibold">ক্যাশ অন ডেলিভারি</span>
                        <span class="block text-xs text-gray-500">পণ্য হাতে পেয়ে ডেলিভারি ম্যানকে টাকা দিন।</span>
                    </span>
                </label>

                @if ($bkashAvailable)
                    <label class="flex cursor-pointer items-start gap-2 rounded-md border px-3 py-2.5
                                  {{ $payment_method === 'bkash' ? 'border-brand-500 bg-brand-50' : 'border-gray-200' }}">
                        <input type="radio" wire:model.live="payment_method" value="bkash" class="mt-1 accent-brand-500">
                        <span>
                            <span class="block font-semibold">বিকাশ</span>
                            <span class="block text-xs text-gray-500">অর্ডার কনফার্ম করলে বিকাশ পেমেন্ট পেজে যাবেন।</span>
                        </span>
                    </label>
                @endif
                @error('payment_method') <span class="text-xs text-error-500">{{ $message }}</span> @enderror
            </section>

            @if ($note)
                <p class="text-xs leading-relaxed text-gray-500 whitespace-pre-line">{{ $note }}</p>
            @endif

            <button type="submit" wire:loading.attr="disabled" wire:target="placeOrder"
                    class="flex w-full items-center justify-center gap-2 rounded-md bg-brand-600 px-4 py-3.5 text-center font-bold text-white shadow transition hover:bg-brand-500 disabled:opacity-60">
                <span wire:loading.remove wire:target="placeOrder">🔒 {{ $buttonText }} @if ($showSummary)@taka($total)@endif</span>
                <span wire:loading wire:target="placeOrder">প্রসেস হচ্ছে…</span>
            </button>
        </div>
    </form>
</div>
