<?php

namespace App\Livewire\Checkout;

use App\Models\Coupon;
use App\Models\IncompleteOrder;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\Payment\BkashService;
use App\Support\Delivery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use RuntimeException;
use Throwable;

class CheckoutForm extends Component
{
    public string $customer_name = '';
    public string $phone = '';
    public string $email = '';
    public string $district = '';
    public string $thana = '';
    public string $postcode = '';
    public string $address = '';
    public string $notes = '';

    public string $payment_method = 'cod';

    /** Logged-in customers: pick a saved address / remember this one. */
    public ?int $savedAddressId = null;
    public bool $saveAddress = false;

    public string $couponCode = '';
    public ?array $appliedCoupon = null; // ['code' => ..., 'discount' => ...]
    public string $couponError = '';

    public string $error = '';

    public function mount(): void
    {
        if (Auth::guard('web')->check()) {
            $this->customer_name = Auth::guard('web')->user()->name;
            $this->phone = Auth::guard('web')->user()->phone;

            if ($default = Auth::guard('web')->user()->addresses()->where('is_default', true)->first()) {
                $this->fillFromAddress($default);
            }
        }

        // GTM begin_checkout — fired once when the checkout page loads with
        // items in the cart, matching the old app's checkout page effect.
        $lines = app(CartService::class)->lines();
        if (! empty($lines)) {
            $this->dispatch('gtm:begin_checkout', ecommerce: [
                'currency' => 'BDT',
                'value' => app(CartService::class)->subtotal(),
                'items' => array_map(fn ($l) => [
                    'item_id' => $l['product']->id,
                    'item_name' => $l['name'],
                    'price' => $l['price'],
                    'quantity' => $l['quantity'],
                ], $lines),
            ]);
        }
    }

    protected function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:7', 'max:20'],
            'email' => ['nullable', 'email'],
            'district' => ['required', 'string'],
            'thana' => ['required', 'string'],
            'postcode' => ['nullable', 'string'],
            'address' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'payment_method' => ['required', 'in:cod,bkash'],
        ];
    }

    public function updatedSavedAddressId($value): void
    {
        $address = $value && Auth::guard('web')->check()
            ? Auth::guard('web')->user()->addresses()->whereKey((int) $value)->first()
            : null;

        if ($address) {
            $this->fillFromAddress($address);
        }
    }

    private function fillFromAddress(\App\Models\CustomerAddress $address): void
    {
        $this->savedAddressId = $address->id;
        $this->customer_name = $address->name;
        $this->phone = $address->phone;
        $this->district = $address->district;
        $this->thana = $address->thana;
        $this->postcode = (string) $address->postcode;
        $this->address = $address->address;
    }

    /** "Save this address" ticked by a logged-in customer: store it (max 5, no duplicates). */
    private function rememberAddress(): void
    {
        if (! $this->saveAddress || ! Auth::guard('web')->check()) {
            return;
        }

        $user = Auth::guard('web')->user();

        $exists = $user->addresses()
            ->where('address', $this->address)
            ->where('district', $this->district)
            ->exists();

        if ($exists || $user->addresses()->count() >= 5) {
            return;
        }

        $user->addresses()->create([
            'label' => $user->addresses()->exists() ? 'ঠিকানা '.($user->addresses()->count() + 1) : 'বাসা',
            'name' => $this->customer_name,
            'phone' => preg_replace('/[^0-9]/', '', $this->phone),
            'district' => $this->district,
            'thana' => $this->thana,
            'postcode' => $this->postcode ?: null,
            'address' => $this->address,
            'is_default' => ! $user->addresses()->exists(),
        ]);
    }

    // Incomplete-order capture: once a valid phone is typed (on blur), remember the
    // lead so an admin can call and convert it if the order is never placed.
    public function updatedPhone(): void
    {
        $this->captureLead();
    }

    public function updatedCustomerName(): void
    {
        $this->captureLead();
    }

    public function updatedAddress(): void
    {
        $this->captureLead();
    }

    private function captureLead(): void
    {
        try {
            $items = [];

            foreach (app(CartService::class)->lines() as $l) {
                if ($l['quantity'] >= 1) {
                    $items[] = [
                        'product_id' => $l['product']->id,
                        'variant_id' => $l['variant']?->id,
                        'name' => $l['name'],
                        'quantity' => $l['quantity'],
                        'unit_price' => (float) $l['price'],
                    ];
                }
            }

            IncompleteOrder::capture(session()->getId(), 'checkout', null, $this->phone, $this->customer_name, $this->district, $this->address, $items, request()->ip());
        } catch (Throwable) {
            // Lead capture must never get in the way of checkout.
        }
    }

    public function applyCoupon(): void
    {
        $this->couponError = '';
        $this->appliedCoupon = null;

        $code = trim($this->couponCode);
        if ($code === '') {
            return;
        }

        $subtotal = app(CartService::class)->subtotal();

        $coupon = Coupon::where('code', $code)->where('is_active', true)->first();

        $valid = $coupon
            && (! $coupon->valid_until || $coupon->valid_until->greaterThanOrEqualTo(now()))
            && ($coupon->max_uses === null || $coupon->uses < $coupon->max_uses)
            && $subtotal >= (float) $coupon->min_spend;

        if (! $valid) {
            $this->couponError = 'কুপন কোডটি সঠিক নয় অথবা শর্ত পূরণ করেনি';

            return;
        }

        $discount = $coupon->discount_type === 'percent'
            ? ($subtotal * (float) $coupon->discount_value) / 100
            : (float) $coupon->discount_value;

        $discount = min($discount, $subtotal);

        $this->appliedCoupon = ['code' => $coupon->code, 'discount' => $discount];
    }

    public function removeCoupon(): void
    {
        $this->appliedCoupon = null;
        $this->couponCode = '';
        $this->couponError = '';
    }

    public function placeOrder(OrderService $orderService, BkashService $bkash)
    {
        $this->error = '';

        // Per-IP limit on order attempts (an order is heavier than a page
        // view: row locks, stock). Failed validation does not count.
        $limitKey = 'checkout-order:'.request()->ip();

        if (RateLimiter::tooManyAttempts($limitKey, 6)) {
            $this->error = 'একটু বেশি বার চেষ্টা করা হয়েছে। '.RateLimiter::availableIn($limitKey).' সেকেন্ড পর আবার চেষ্টা করুন।';

            return null;
        }

        $this->validate();

        RateLimiter::hit($limitKey, 600);

        $isBkash = $this->payment_method === 'bkash';

        if ($isBkash && ! $bkash->configured()) {
            $this->error = 'বিকাশ পেমেন্ট এই মুহূর্তে চালু নেই। অনুগ্রহ করে ক্যাশ অন ডেলিভারি বেছে নিন।';

            return null;
        }

        $cart = app(CartService::class);

        // CartService clamps a line's quantity to current stock, so an
        // out-of-stock line comes back with quantity 0. Those must not reach
        // OrderService — an all-out-of-stock cart used to produce an order
        // of zero-quantity items charged only the delivery fee.
        $lines = array_values(array_filter($cart->lines(), fn ($l) => $l['quantity'] >= 1));

        if (empty($lines)) {
            $this->error = $cart->lines() === []
                ? 'আপনার কার্টটি খালি।'
                : 'আপনার কার্টের পণ্যগুলো এই মুহূর্তে স্টকে নেই।';

            return null;
        }

        $cartLines = array_map(fn ($l) => [
            'product_id' => $l['product']->id,
            'variant_id' => $l['variant']?->id,
            'quantity' => $l['quantity'],
        ], $lines);

        try {
            $order = $orderService->createOrder($cartLines, [
                'customer_name' => $this->customer_name,
                'phone' => $this->phone,
                'email' => $this->email ?: null,
                'district' => $this->district,
                'thana' => $this->thana,
                'postcode' => $this->postcode ?: null,
                'address' => $this->address,
                'notes' => $this->notes,
                'ip_address' => request()->ip(),
                // COD or bKash (validated in rules()). SSLCommerz is still
                // shown in the UI as disabled/"coming soon" only.
                'payment_method' => $this->payment_method,
                // bKash: don't touch stock until payment is confirmed
                // (OrderService::finalizeBkashPayment), so an abandoned or
                // failed payment never holds stock.
            ], $this->appliedCoupon['code'] ?? null, null, reserveStock: ! $isBkash);
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        if ($isBkash) {
            try {
                // Amount sent to bKash is $order->total, computed server-side
                // by OrderService from DB prices — never client input.
                $payment = $bkash->createPayment($order);
            } catch (RuntimeException $e) {
                $orderService->markBkashFailed($order, $e->getMessage());
                $this->error = 'বিকাশ পেমেন্ট শুরু করা যায়নি। অনুগ্রহ করে আবার চেষ্টা করুন অথবা ক্যাশ অন ডেলিভারি বেছে নিন।';

                return null;
            }

            // Cart is intentionally NOT cleared here — if the customer
            // cancels or the payment fails, they come back to a full cart.
            // BkashCallbackController clears it only on confirmed success.
            return redirect()->away($payment['bkashURL']);
        }

        // The Purchase event is fired from the order confirmation page
        // (OrderController::show → order.show), built from the stored order.

        $this->rememberAddress();

        try {
            IncompleteOrder::markConverted($this->phone, $order->id);
        } catch (Throwable) {
        }

        $cart->clear();

        return redirect()->route('order.show', $order->order_token);
    }

    public function render(CartService $cart, BkashService $bkash)
    {
        return view('livewire.checkout.checkout-form', [
            'lines' => $cart->lines(),
            'subtotal' => $cart->subtotal(),
            'shippingFee' => Delivery::quote($this->district ?: null, $cart->subtotal()),
            'freeRemaining' => Delivery::remainingForFree($cart->subtotal()),
            'districts' => config('districts'),
            'bkashAvailable' => $bkash->configured(),
            'savedAddresses' => Auth::guard('web')->check() ? Auth::guard('web')->user()->addresses()->orderByDesc('is_default')->get() : collect(),
        ]);
    }
}
