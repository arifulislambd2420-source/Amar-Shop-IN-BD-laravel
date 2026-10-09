@php
    $buyable = array_values(array_filter($lines, fn ($l) => $l['quantity'] >= 1));
    $itemCount = array_sum(array_column($buyable, 'quantity'));
    $discount = (float) ($appliedCoupon['discount'] ?? 0);
    $total = max(0, $subtotal + ($shippingFee ?? 0) - $discount);
    $uniform = $dhakaFee == $outsideFee;
    $input = 'block h-12 w-full rounded-xl border bg-white px-4 text-base text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-4';
    $ok = 'border-gray-300 focus:border-brand-500 focus:ring-brand-100';
    $bad = 'border-error-500 bg-error-50/40 focus:border-error-500 focus:ring-error-50';
    $label = 'mb-1.5 block text-sm font-medium text-gray-700';
    $card = 'rounded-2xl border border-gray-200 bg-white p-4 sm:p-5';
    $fee = fn ($v) => $v == 0 ? 'ফ্রি' : \App\Support\Money::taka($v);
@endphp

<div x-data
    x-on:checkout-invalid.window="setTimeout(() => { const el = document.querySelector('[aria-invalid=true], #checkout-error'); if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.focus?.({ preventScroll: true }); } }, 60)">

@if($lines === [])
    <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center">
        <p class="text-lg font-semibold text-gray-800">আপনার কার্টটি খালি</p>
        <p class="mt-1 text-sm text-gray-500">চেকআউট করতে আগে কিছু পণ্য কার্টে যোগ করুন।</p>
        <a href="{{ route('shop') }}" class="mt-6 inline-flex h-11 items-center rounded-xl bg-brand-500 px-6 text-sm font-semibold text-white hover:bg-brand-600">কেনাকাটা শুরু করুন</a>
    </div>
@else
<div class="grid gap-5 lg:grid-cols-[1fr_380px] lg:gap-8">
    {{-- Summary: first on phones (collapsed), sticky sidebar on desktop --}}
    <aside class="lg:order-2" x-data="{ open: false }">
        <div class="{{ $card }} lg:sticky lg:top-36">
            <button type="button" @click="open = ! open" :aria-expanded="open.toString()" class="-my-2 flex min-h-11 w-full items-center justify-between gap-3 text-left lg:pointer-events-none">
                <span class="font-semibold text-gray-900">অর্ডার সারাংশ <span class="font-normal text-gray-500">({{ $itemCount }}টি পণ্য)</span></span>
                <span class="flex items-center gap-2 lg:hidden">
                    <span class="font-bold text-brand-600 tabular-nums">@taka($total)</span>
                    <svg class="transition-transform" :class="open && 'rotate-180'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg>
                </span>
            </button>

            <div class="mt-4 lg:block" :class="open ? 'block' : 'hidden'">
                <ul class="mb-4 max-h-72 space-y-3 overflow-y-auto">
                    @foreach($lines as $line)
                        <li class="flex gap-3" wire:key="co-line-{{ $line['key'] }}">
                            <div class="relative h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-surface">
                                @if($line['image'])<img src="{{ $line['image'] }}" alt="" width="56" height="56" loading="lazy" class="h-full w-full object-cover">@endif
                                @if($line['quantity'] >= 1)
                                    <span class="absolute -right-0 -top-0 inline-flex h-5 min-w-5 items-center justify-center rounded-bl-lg bg-gray-800 px-1 text-[11px] font-bold text-white">{{ $line['quantity'] }}</span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1 text-sm">
                                <p class="line-clamp-2 font-medium text-gray-800">{{ $line['product']->name }}</p>
                                @if($line['variant'])<p class="text-xs text-gray-500">সাইজ: {{ $line['variant']->label }}</p>@endif
                                @if($line['quantity'] < 1)<p class="text-xs font-medium text-error-500">স্টক নেই — অর্ডারে যাবে না</p>@endif
                            </div>
                            <span class="shrink-0 text-sm font-semibold tabular-nums">@taka($line['line_total'])</span>
                        </li>
                    @endforeach
                </ul>

                <dl class="space-y-2 border-t border-gray-100 pt-4 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-600">সাবটোটাল</dt><dd class="font-semibold tabular-nums">@taka($subtotal)</dd></div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-600">ডেলিভারি চার্জ @if(filled($district))<span class="text-gray-400">({{ \App\Support\Delivery::isDhaka($district) ? 'ঢাকার ভেতরে' : 'ঢাকার বাইরে' }})</span>@endif</dt>
                        <dd class="text-right font-semibold tabular-nums">
                            @if(is_null($shippingFee))
                                <span class="text-xs font-normal text-gray-500">এলাকা বাছাই করুন</span>
                            @elseif($shippingFee == 0)
                                <span class="text-success-600">ফ্রি</span>
                            @else
                                @taka($shippingFee)
                            @endif
                        </dd>
                    </div>
                    @if($discount > 0)
                        <div class="flex justify-between"><dt class="text-gray-600">কুপন ছাড় ({{ $appliedCoupon['code'] }})</dt><dd class="font-semibold text-success-600 tabular-nums">−@taka($discount)</dd></div>
                    @endif
                    <div class="flex justify-between border-t border-gray-100 pt-3 text-lg font-bold">
                        <dt>মোট</dt><dd class="text-brand-600 tabular-nums">@taka($total)</dd>
                    </div>
                    @if(is_null($shippingFee))
                        <p class="text-xs text-gray-500">ডেলিভারি এলাকা বাছাই করলে চার্জ যোগ হবে।</p>
                    @endif
                </dl>
                @if($freeRemaining)
                    <p class="mt-3 rounded-xl bg-success-50 px-3 py-2 text-xs font-medium text-success-700">আর @taka($freeRemaining) এর পণ্য কিনলে ডেলিভারি ফ্রি!</p>
                @endif
            </div>
        </div>
    </aside>

    <form id="checkout-form" wire:submit="placeOrder" class="flex flex-col gap-5 lg:order-1" novalidate>
        @if($error)
            <div id="checkout-error" tabindex="-1" class="flex items-start gap-3 rounded-2xl border border-error-200 bg-error-50 p-4 text-sm font-medium text-error-500" role="alert">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="mt-px shrink-0"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
                <p>{{ $error }}</p>
            </div>
        @endif

        {{-- 1. Customer --}}
        <section class="{{ $card }} space-y-4" aria-labelledby="co-you">
            <h2 id="co-you" class="flex items-center gap-2 text-base font-bold"><span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-xs text-white">১</span> আপনার তথ্য</h2>

            @if($savedAddresses->isNotEmpty())
                <div>
                    <label for="co-saved" class="{{ $label }}">সেভ করা ঠিকানা</label>
                    <select id="co-saved" wire:model.live="savedAddressId" class="{{ $input }} {{ $ok }}">
                        <option value="">— নতুন ঠিকানা লিখব —</option>
                        @foreach($savedAddresses as $saved)
                            <option value="{{ $saved->id }}">{{ $saved->label }} — {{ \Illuminate\Support\Str::limit($saved->address, 30) }}, {{ $saved->district }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label for="co-name" class="{{ $label }}">আপনার নাম <span class="text-error-500">*</span></label>
                <input id="co-name" type="text" wire:model.blur="customer_name" autocomplete="name" placeholder="পূর্ণ নাম"
                    @error('customer_name') aria-invalid="true" @enderror class="{{ $input }} {{ $errors->has('customer_name') ? $bad : $ok }}">
                @error('customer_name')<p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="co-phone" class="{{ $label }}">মোবাইল নম্বর <span class="text-error-500">*</span></label>
                <input id="co-phone" type="tel" wire:model.blur="phone" inputmode="numeric" autocomplete="tel" maxlength="14" placeholder="01XXXXXXXXX"
                    @error('phone') aria-invalid="true" @enderror class="{{ $input }} {{ $errors->has('phone') ? $bad : $ok }}">
                @error('phone')<p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>@else<p class="mt-1.5 text-xs text-gray-500">ডেলিভারির আগে এই নম্বরে কল করা হবে।</p>@enderror
            </div>
        </section>

        {{-- 2. Delivery --}}
        <section class="{{ $card }} space-y-4" aria-labelledby="co-delivery">
            <h2 id="co-delivery" class="flex items-center gap-2 text-base font-bold"><span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-xs text-white">২</span> ডেলিভারি ঠিকানা</h2>

            @unless($uniform)
                <fieldset>
                    <legend class="{{ $label }}">ডেলিভারি এলাকা <span class="text-error-500">*</span></legend>
                    <div class="grid grid-cols-2 gap-3" @error('district') aria-invalid="true" tabindex="-1" @enderror>
                        @foreach(['dhaka' => ['ঢাকার ভেতরে', $dhakaFee], 'outside' => ['ঢাকার বাইরে', $outsideFee]] as $value => [$text, $areaFee])
                            <label @class([
                                'relative flex min-h-[4.5rem] cursor-pointer flex-col justify-center rounded-xl border-2 p-3 transition',
                                'border-brand-500 bg-brand-50' => $area === $value,
                                'border-gray-200 bg-white hover:border-gray-300' => $area !== $value,
                                'border-error-500' => $area === '' && $errors->has('district'),
                            ])>
                                <input type="radio" wire:model.live="area" value="{{ $value }}" class="sr-only">
                                <span class="text-sm font-semibold text-gray-900">{{ $text }}</span>
                                <span class="mt-0.5 text-sm font-bold tabular-nums {{ $areaFee == 0 ? 'text-success-600' : 'text-brand-600' }}">ডেলিভারি {{ $fee($areaFee) }}</span>
                                @if($area === $value)
                                    <span class="absolute right-2.5 top-2.5 inline-flex h-5 w-5 items-center justify-center rounded-full bg-brand-500 text-white" aria-hidden="true">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
                                    </span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                    @error('district')@if($area === '')<p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>@endif @enderror
                </fieldset>
            @else
                <p class="rounded-xl bg-surface px-4 py-3 text-sm text-gray-700">সারা দেশে ডেলিভারি চার্জ: <span class="font-bold text-brand-600">{{ $fee($dhakaFee) }}</span></p>
            @endunless

            @if($uniform || $area === 'outside')
                <div>
                    <label for="co-district" class="{{ $label }}">জেলা <span class="text-error-500">*</span></label>
                    <select id="co-district" wire:model.live="district" @error('district') aria-invalid="true" @enderror class="{{ $input }} {{ $errors->has('district') ? $bad : $ok }}">
                        <option value="">জেলা বাছাই করুন</option>
                        @foreach($districts as $d)
                            @continue(! $uniform && \App\Support\Delivery::isDhaka($d))
                            <option value="{{ $d }}">{{ $d }}</option>
                        @endforeach
                    </select>
                    @error('district')<p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>@enderror
                </div>
            @endif

            <div>
                <label for="co-thana" class="{{ $label }}">থানা / উপজেলা <span class="text-error-500">*</span></label>
                <input id="co-thana" type="text" wire:model.blur="thana" placeholder="যেমন: মিরপুর" maxlength="100"
                    @error('thana') aria-invalid="true" @enderror class="{{ $input }} {{ $errors->has('thana') ? $bad : $ok }}">
                @error('thana')<p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="co-address" class="{{ $label }}">বিস্তারিত ঠিকানা <span class="text-error-500">*</span></label>
                <textarea id="co-address" rows="3" wire:model.blur="address" autocomplete="street-address" maxlength="500" placeholder="বাসা/ফ্ল্যাট নং, রোড, এলাকা"
                    @error('address') aria-invalid="true" @enderror class="block w-full rounded-xl border bg-white px-4 py-3 text-base text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-4 {{ $errors->has('address') ? $bad : $ok }}"></textarea>
                @error('address')<p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>@enderror
            </div>

            <details class="group rounded-xl border border-gray-200" @if($errors->hasAny(['email', 'postcode', 'notes']) || filled($email) || filled($notes)) open @endif>
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between px-4 text-sm font-medium text-gray-700">
                    অতিরিক্ত তথ্য (ঐচ্ছিক)
                    <svg class="transition-transform group-open:rotate-180" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg>
                </summary>
                <div class="space-y-4 border-t border-gray-100 p-4">
                    <div>
                        <label for="co-email" class="{{ $label }}">ইমেইল</label>
                        <input id="co-email" type="email" wire:model.blur="email" autocomplete="email" @error('email') aria-invalid="true" @enderror class="{{ $input }} {{ $errors->has('email') ? $bad : $ok }}">
                        @error('email')<p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="co-postcode" class="{{ $label }}">পোস্ট কোড</label>
                        <input id="co-postcode" type="text" wire:model.blur="postcode" inputmode="numeric" maxlength="20" class="{{ $input }} {{ $ok }}">
                    </div>
                    <div>
                        <label for="co-notes" class="{{ $label }}">অর্ডার নোট</label>
                        <textarea id="co-notes" rows="2" wire:model.blur="notes" maxlength="1000" placeholder="যেমন: বিকেলে ডেলিভারি দিন" class="block w-full rounded-xl border bg-white px-4 py-3 text-base focus:outline-none focus:ring-4 {{ $errors->has('notes') ? $bad : $ok }}"></textarea>
                        @error('notes')<p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>@enderror
                    </div>
                </div>
            </details>

            @auth('web')
                <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm text-gray-700">
                    <input type="checkbox" wire:model="saveAddress" class="h-5 w-5 rounded accent-brand-500"> এই ঠিকানাটি পরের বারের জন্য সেভ করুন
                </label>
            @endauth
        </section>

        {{-- 3. Payment + coupon --}}
        <section class="{{ $card }} space-y-4" aria-labelledby="co-pay">
            <h2 id="co-pay" class="flex items-center gap-2 text-base font-bold"><span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-xs text-white">৩</span> পেমেন্ট</h2>
            <div class="space-y-3">
                @foreach(array_filter(['cod' => ['ক্যাশ অন ডেলিভারি', 'পণ্য হাতে পেয়ে টাকা দিন'], 'bkash' => $bkashAvailable ? ['বিকাশ', 'অর্ডারের পর বিকাশে পেমেন্ট করুন'] : null]) as $value => [$title, $sub])
                    <label @class(['flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border-2 px-4 py-3 transition', 'border-brand-500 bg-brand-50' => $payment_method === $value, 'border-gray-200 hover:border-gray-300' => $payment_method !== $value])>
                        <input type="radio" wire:model.live="payment_method" value="{{ $value }}" class="h-5 w-5 shrink-0 accent-brand-500">
                        <span><span class="block font-semibold text-gray-900">{{ $title }}</span><span class="text-xs text-gray-500">{{ $sub }}</span></span>
                    </label>
                @endforeach
                @error('payment_method')<p class="text-sm text-error-500">{{ $message }}</p>@enderror
            </div>

            <div x-data="{ open: {{ $appliedCoupon || $couponError ? 'true' : 'false' }} }" class="border-t border-gray-100 pt-4">
                <button type="button" x-show="! open" @click="open = true" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand-600">কুপন কোড আছে?</button>
                <div x-show="open" x-cloak>
                    <label for="co-coupon" class="{{ $label }}">কুপন কোড</label>
                    @if($appliedCoupon)
                        <div class="flex items-center justify-between gap-3 rounded-xl bg-success-50 px-4 py-3">
                            <p class="text-sm font-medium text-success-700">✓ <span class="font-bold">{{ $appliedCoupon['code'] }}</span> — @taka($discount) ছাড় পেয়েছেন</p>
                            <button type="button" wire:click="removeCoupon" class="inline-flex min-h-10 shrink-0 items-center px-2 text-sm font-semibold text-gray-600 underline">সরান</button>
                        </div>
                    @else
                        <div class="flex h-12 gap-2">
                            <input id="co-coupon" type="text" wire:model="couponCode" wire:keydown.enter.prevent="applyCoupon" autocomplete="off" placeholder="কোড লিখুন"
                                @if($couponError) aria-invalid="true" @endif class="h-12 min-w-0 flex-1 rounded-xl border bg-white px-4 text-base uppercase focus:outline-none focus:ring-4 {{ $couponError ? $bad : $ok }}">
                            <button type="button" wire:click="applyCoupon" wire:loading.attr="disabled" wire:target="applyCoupon" class="h-12 shrink-0 rounded-xl bg-secondary px-5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60">প্রয়োগ করুন</button>
                        </div>
                        @if($couponError)<p class="mt-1.5 text-sm text-error-500">{{ $couponError }}</p>@endif
                    @endif
                </div>
            </div>
        </section>

        <div class="hidden md:block">
            <button type="submit" wire:loading.attr="disabled" wire:target="placeOrder"
                class="inline-flex h-14 w-full items-center justify-center gap-2 rounded-xl bg-brand-500 text-lg font-bold text-white shadow-sm hover:bg-brand-600 disabled:opacity-70">
                <span wire:loading.remove wire:target="placeOrder">অর্ডার নিশ্চিত করুন — @taka($total)</span>
                <span wire:loading wire:target="placeOrder">অর্ডার হচ্ছে...</span>
            </button>
            <p class="mt-3 text-center text-xs text-gray-500">অর্ডার নিশ্চিত করলে আপনি আমাদের <a href="{{ route('terms') }}" class="underline">শর্তাবলী</a> মেনে নিচ্ছেন।</p>
        </div>
    </form>
</div>

{{-- Phones/tablets: the total and the order button are always on screen. --}}
<div class="sticky-cta md:hidden fixed inset-x-3 bottom-above-mobile-nav z-30 flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-2 pl-4 shadow-[0_8px_24px_-8px_rgba(15,23,42,0.25)]">
    <div class="min-w-0 flex-1 leading-tight">
        <p class="text-xs text-gray-500">মোট @if(is_null($shippingFee))<span class="text-gray-400">(ডেলিভারি ছাড়া)</span>@endif</p>
        <p class="text-lg font-bold text-brand-600 tabular-nums">@taka($total)</p>
    </div>
    <button type="submit" form="checkout-form" wire:loading.attr="disabled" wire:target="placeOrder"
        class="inline-flex h-12 shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-500 px-5 font-bold text-white disabled:opacity-70">
        <span wire:loading.remove wire:target="placeOrder">অর্ডার নিশ্চিত করুন</span>
        <span wire:loading wire:target="placeOrder">অর্ডার হচ্ছে...</span>
    </button>
</div>
@endif
</div>
