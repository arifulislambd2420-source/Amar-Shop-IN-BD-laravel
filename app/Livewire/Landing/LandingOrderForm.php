<?php

namespace App\Livewire\Landing;

use App\Models\LandingPage;
use App\Services\OrderService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use RuntimeException;

/**
 * The order form embedded at the bottom of every landing page template.
 * Deliberately simpler than the full checkout form (App\Livewire\Checkout\
 * CheckoutForm): a landing page sells exactly one product, so there is no
 * cart/district/thana breakdown — just name, phone and a free-text address,
 * as specced. Still goes through the same OrderService::createOrder(), so
 * stock locking, coupon-free totals and price integrity are identical.
 */
class LandingOrderForm extends Component
{
    public int $landingPageId;
    public string $buttonText = 'অর্ডার কনফার্ম করুন';

    public string $customer_name = '';
    public string $phone = '';
    public string $address = '';
    public int $quantity = 1;

    public string $error = '';

    public function mount(LandingPage $landingPage): void
    {
        $this->landingPageId = $landingPage->id;
        $this->buttonText = $landingPage->button_text ?: $this->buttonText;
    }

    protected function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:7', 'max:20'],
            'address' => ['required', 'string', 'max:1000'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function placeOrder(OrderService $orderService)
    {
        $this->error = '';

        // Rate limit per IP + per page, so a script can't hammer this form
        // (order-creation is heavier than a normal page view: DB writes,
        // row locks, stock decrements).
        $key = 'landing-order:'.request()->ip().':'.$this->landingPageId;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->error = "একটু বেশি বার চেষ্টা করা হয়েছে। {$seconds} সেকেন্ড পর আবার চেষ্টা করুন।";

            return null;
        }

        $this->validate();

        $landingPage = LandingPage::with('product')->find($this->landingPageId);

        if (! $landingPage || ! $landingPage->is_active) {
            $this->error = 'এই পেজটি এখন সক্রিয় নেই।';

            return null;
        }

        // ->product (not just product_id): the relation excludes a
        // soft-deleted product, which used to surface to the customer as
        // OrderService's raw English "Product N not found".
        if (! $landingPage->product) {
            $this->error = 'এই মুহূর্তে অর্ডার করা যাচ্ছে না — পণ্য যুক্ত করা নেই।';

            return null;
        }

        RateLimiter::hit($key, 600); // 10 minutes

        $cartLines = [[
            'product_id' => $landingPage->product_id,
            'variant_id' => null,
            'quantity' => $this->quantity,
            // Server-side price only: the page's admin-set override (or
            // null to fall back to the product's own price), never
            // anything read from the request.
            'unit_price_override' => $landingPage->price_override !== null
                ? (float) $landingPage->price_override
                : null,
        ]];

        try {
            $order = $orderService->createOrder($cartLines, [
                'customer_name' => $this->customer_name,
                'phone' => $this->phone,
                'email' => null,
                'district' => 'N/A',
                'thana' => 'N/A',
                'postcode' => null,
                'address' => $this->address,
                'notes' => 'Landing page: '.$landingPage->title,
                'ip_address' => request()->ip(),
                'payment_method' => 'cod',
            ], null, $landingPage->id);
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        return redirect()->route('order.show', $order->order_token);
    }

    public function render()
    {
        return view('livewire.landing.landing-order-form');
    }
}
