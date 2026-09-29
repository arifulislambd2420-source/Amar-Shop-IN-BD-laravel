<?php

namespace App\Livewire\Checkout;

use App\Models\Coupon;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\Payment\BkashService;
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
        $this->validate();

        $isBkash = $this->payment_method === 'bkash';

        if ($isBkash && ! $bkash->configured()) {
            $this->error = 'বিকাশ পেমেন্ট এই মুহূর্তে চালু নেই। অনুগ্রহ করে ক্যাশ অন ডেলিভারি বেছে নিন।';

            return null;
        }

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

        // GTM purchase — flashed to session and fired once from the order
        // confirmation page (order.show), rather than from here: a Livewire
        // action that both dispatches a browser event AND redirects on the
        // same response can lose the event to the navigation, so the
        // confirmation page is the reliable place to fire it exactly once.
        session()->flash('gtm_purchase', [
            'transaction_id' => $order->order_token,
            'value' => (float) $order->total,
            'currency' => 'BDT',
            'shipping' => (float) $order->shipping_fee,
            'coupon' => $this->appliedCoupon['code'] ?? null,
            'items' => $order->items->map(fn ($item) => [
                'item_id' => $item->product_id,
                'item_name' => $item->product_name,
                'price' => (float) $item->unit_price,
                'quantity' => $item->quantity,
            ])->all(),
        ]);

        $cart->clear();

        return redirect()->route('order.show', $order->order_token);
    }

    public function render(CartService $cart, BkashService $bkash)
    {
        return view('livewire.checkout.checkout-form', [
            'lines' => $cart->lines(),
            'subtotal' => $cart->subtotal(),
            'shippingFee' => OrderService::SHIPPING_FEE,
            'districts' => config('districts'),
            'bkashAvailable' => $bkash->configured(),
        ]);
    }
}
