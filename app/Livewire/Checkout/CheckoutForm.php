<?php

namespace App\Livewire\Checkout;

use App\Models\Coupon;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use RuntimeException;

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

    public string $couponCode = '';
    public ?array $appliedCoupon = null; // ['code' => ..., 'discount' => ...]
    public string $couponError = '';

    public string $error = '';

    public function mount(): void
    {
        if (Auth::guard('web')->check()) {
            $this->customer_name = Auth::guard('web')->user()->name;
            $this->phone = Auth::guard('web')->user()->phone;
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
        ];
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

    public function placeOrder(OrderService $orderService)
    {
        $this->error = '';
        $this->validate();

        $cart = app(CartService::class);
        $lines = $cart->lines();

        if (empty($lines)) {
            $this->error = 'আপনার কার্টটি খালি।';

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
                // Only COD is a real, working payment method right now — the
                // others are shown in the UI as disabled/cosmetic, mirroring
                // the old app's mock bkash/sslcommerz/advance flows which
                // never actually charged anyone (Phase 6: real gateways).
                'payment_method' => 'cod',
            ], $this->appliedCoupon['code'] ?? null);
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        $cart->clear();

        return redirect()->route('order.show', $order->order_token);
    }

    public function render(CartService $cart)
    {
        return view('livewire.checkout.checkout-form', [
            'lines' => $cart->lines(),
            'subtotal' => $cart->subtotal(),
            'shippingFee' => OrderService::SHIPPING_FEE,
            'districts' => config('districts'),
        ]);
    }
}
