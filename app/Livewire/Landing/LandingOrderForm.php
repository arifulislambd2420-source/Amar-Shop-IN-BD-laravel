<?php

namespace App\Livewire\Landing;

use App\Models\LandingPage;
use App\Services\OrderService;
use App\Support\Delivery;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

/**
 * The order form embedded at the bottom of every landing page template.
 * Deliberately simpler than the full checkout form (App\Livewire\Checkout\
 * CheckoutForm): a landing page sells one product (or one of its packages),
 * so there is no cart/district/thana breakdown — just name, phone and a
 * free-text address. Still goes through the same OrderService::createOrder(),
 * so stock locking, coupon-free totals and price integrity are identical.
 *
 * Block-builder pages add three optional things, all read from the DB on
 * every request and never trusted from the browser:
 *  - packages (the page's own price list: product, label, price, quantity),
 *    chosen by index,
 *  - size/colour pickers from the page's visible "variants" block,
 *  - an inside/outside Dhaka choice when the two delivery charges differ.
 */
class LandingOrderForm extends Component
{
    public int $landingPageId;
    public string $buttonText = 'অর্ডার কনফার্ম করুন';
    public string $heading = 'এখনই অর্ডার করুন';
    public string $rootId = 'order-form';
    public string $note = '';

    public string $customer_name = '';
    public string $phone = '';
    public string $address = '';
    public int $quantity = 1;

    public int $packageIndex = 0;
    public string $zone = 'dhaka';
    public string $size = '';
    public string $color = '';

    public string $error = '';

    public function mount(LandingPage $landingPage, ?string $buttonText = null, ?string $heading = null, ?string $rootId = null, ?string $note = null): void
    {
        $this->landingPageId = $landingPage->id;
        $this->buttonText = $buttonText ?: ($landingPage->button_text ?: $this->buttonText);

        if ($heading !== null) {
            $this->heading = $heading;
        }
        if ($rootId) {
            $this->rootId = $rootId;
        }
        $this->note = (string) $note;
    }

    protected function rules(): array
    {
        $rules = [
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:7', 'max:20'],
            'address' => ['required', 'string', 'max:1000'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'zone' => ['required', 'in:dhaka,outside'],
        ];

        $variants = $this->variantConfig($this->page());

        foreach (['size' => 'sizes', 'color' => 'colors'] as $field => $listKey) {
            $options = $variants[$listKey] ?? [];

            if ($options) {
                $rules[$field] = [($variants[$field.'_required'] ?? false) ? 'required' : 'nullable', Rule::in($options)];
            }
        }

        return $rules;
    }

    private function page(): LandingPage
    {
        return LandingPage::with('product')->findOrFail($this->landingPageId);
    }

    /** Valid packages of the page, keyed by their position in the admin list. */
    private function packages(LandingPage $page): array
    {
        if (LandingPage::isLegacyTemplate($page->template)) {
            return [];
        }

        return collect((array) $page->packages)
            ->filter(fn ($p) => is_array($p) && filled($p['product_id'] ?? null) && is_numeric($p['price'] ?? null))
            ->values()
            ->all();
    }

    /** Settings of the page's visible "variants" block (sizes/colors), or []. */
    private function variantConfig(LandingPage $page): array
    {
        if (LandingPage::isLegacyTemplate($page->template)) {
            return [];
        }

        $block = collect($page->visibleBlocks())->firstWhere('type', 'variants');
        $data = $block['data'] ?? [];

        return [
            'sizes' => array_values(array_filter((array) ($data['sizes'] ?? []))),
            // Tag colours plus the names of the image grid's options.
            'colors' => array_values(array_unique(array_merge(
                array_filter((array) ($data['colors'] ?? [])),
                array_filter(array_map(fn ($o) => is_array($o) ? ($o['name'] ?? null) : null, (array) ($data['options'] ?? []))),
            ))),
            'size_label' => $data['size_label'] ?? 'সাইজ নির্বাচন করুন',
            'color_label' => $data['color_label'] ?? 'কালার নির্বাচন করুন',
            'size_required' => (bool) ($data['size_required'] ?? true),
            'color_required' => (bool) ($data['color_required'] ?? false),
        ];
    }

    /** The chosen package, or null when the page has none (single-product mode). */
    private function selectedPackage(array $packages): ?array
    {
        return $packages[$this->packageIndex] ?? ($packages[0] ?? null);
    }

    /** Dhaka/outside only matters when the two charges differ; otherwise the old "N/A". */
    private function districtForZone(): string
    {
        if (Delivery::isUniform()) {
            return 'N/A';
        }

        return $this->zone === 'dhaka' ? Delivery::DHAKA : 'ঢাকার বাইরে';
    }

    /** Fired by an option card's order button in the variants grid (browser event). */
    #[On('select-variant')]
    public function selectVariant(string $color = ''): void
    {
        if (in_array($color, $this->variantConfig($this->page())['colors'] ?? [], true)) {
            $this->color = $color;
        }
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

        $landingPage = $this->page();

        if (! $landingPage->is_active) {
            $this->error = 'এই পেজটি এখন সক্রিয় নেই।';

            return null;
        }

        $packages = $this->packages($landingPage);
        $package = $this->selectedPackage($packages);

        if ($package) {
            $productId = (int) $package['product_id'];
            $quantity = max(1, (int) ($package['quantity'] ?? 1));
            // Package price is the total for the package; OrderService multiplies
            // a per-unit price by quantity, so hand it price ÷ quantity.
            $unitPrice = (float) $package['price'] / $quantity;
            $packageNote = ' | প্যাকেজ: '.($package['label'] ?? '');
        } else {
            // ->product (not just product_id): the relation excludes a
            // soft-deleted product, which used to surface to the customer as
            // OrderService's raw English "Product N not found".
            if (! $landingPage->product) {
                $this->error = 'এই মুহূর্তে অর্ডার করা যাচ্ছে না — পণ্য যুক্ত করা নেই।';

                return null;
            }

            $productId = $landingPage->product_id;
            $quantity = $this->quantity;
            // Server-side price only: the page's admin-set override (or
            // null to fall back to the product's own price), never
            // anything read from the request.
            $unitPrice = $landingPage->price_override !== null ? (float) $landingPage->price_override : null;
            $packageNote = '';
        }

        RateLimiter::hit($key, 600); // 10 minutes

        $options = array_filter([
            'সাইজ' => $this->size,
            'কালার' => $this->color,
        ]);
        $variantNote = $options ? ' | '.implode(', ', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($options), $options)) : '';

        try {
            $order = $orderService->createOrder([[
                'product_id' => $productId,
                'variant_id' => null,
                'quantity' => $quantity,
                'unit_price_override' => $unitPrice,
            ]], [
                'customer_name' => $this->customer_name,
                'phone' => $this->phone,
                'email' => null,
                'district' => $this->districtForZone(),
                'thana' => 'N/A',
                'postcode' => null,
                'address' => $this->address,
                'notes' => 'Landing page: '.$landingPage->title.$packageNote.$variantNote,
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
        $page = $this->page();
        $packages = $this->packages($page);
        $package = $this->selectedPackage($packages);

        if ($package) {
            $subtotal = (float) $package['price'];
        } else {
            $subtotal = $page->effectivePrice() * max(1, $this->quantity);
        }

        $district = Delivery::isUniform() ? null : ($this->zone === 'dhaka' ? Delivery::DHAKA : 'ঢাকার বাইরে');

        return view('livewire.landing.landing-order-form', [
            'packages' => $packages,
            'package' => $package,
            'variants' => $this->variantConfig($page),
            'subtotal' => $subtotal,
            'showSummary' => $subtotal > 0,
            'zoneChoice' => ! Delivery::isUniform() && ! Delivery::qualifiesForFree($subtotal),
            'shippingFee' => Delivery::fee($district, $subtotal),
            'dhakaFee' => Delivery::dhakaFee(),
            'outsideFee' => Delivery::outsideFee(),
        ]);
    }
}
