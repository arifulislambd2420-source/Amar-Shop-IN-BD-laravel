<?php

namespace App\Livewire\Landing;

use App\Models\IncompleteOrder;
use App\Models\LandingPage;
use App\Services\OrderService;
use App\Services\Payment\BkashService;
use App\Support\Delivery;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;
use Throwable;

/**
 * The order form embedded in every landing page template.
 *
 * Legacy templates (template-1/2/3) keep the original compact form
 * (landing-order-form-simple). Block-builder templates (green/purple/cream)
 * get the full two-column form: package cards with +/- quantity, name /
 * address / phone, optional size & colour fields (switched on per page in
 * the "variants" block), inside/outside Dhaka, and an order summary with
 * COD (+ bKash when configured) and the confirm button.
 *
 * Everything that affects money — which package, its price and quantity,
 * the delivery charge — is re-read from the database on every request;
 * the browser only ever sends a package *index*, a quantity and a zone.
 * Orders still go through OrderService::createOrder().
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
    public string $payment_method = 'cod';

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
            'payment_method' => ['required', 'in:cod,bkash'],
        ];

        $variants = $this->variantConfig($this->page());

        foreach (['size', 'color'] as $field) {
            if (! $variants[$field.'_enabled']) {
                continue;
            }

            $options = $variants[$field.'_options'];

            $rules[$field] = [
                $variants[$field.'_required'] ? 'required' : 'nullable',
                $options ? Rule::in($options) : 'string',
                'max:100',
            ];
        }

        return $rules;
    }

    private function page(): LandingPage
    {
        return LandingPage::with('product')->findOrFail($this->landingPageId);
    }

    private function isBlockPage(LandingPage $page): bool
    {
        return ! LandingPage::isLegacyTemplate($page->template);
    }

    /** Valid packages of the page, keyed by their position in the admin list. */
    private function packages(LandingPage $page): array
    {
        if (! $this->isBlockPage($page)) {
            return [];
        }

        return collect((array) $page->packages)
            ->filter(fn ($p) => is_array($p) && filled($p['product_id'] ?? null) && is_numeric($p['price'] ?? null))
            ->values()
            ->all();
    }

    /**
     * Size / colour field settings from the page's visible "variants" block.
     * A field is shown when switched on (or, for blocks saved before the
     * switch existed, when it has options). With options it is a dropdown,
     * without them a free-text box.
     */
    private function variantConfig(LandingPage $page): array
    {
        $data = [];

        if ($this->isBlockPage($page)) {
            $data = collect($page->visibleBlocks())->firstWhere('type', 'variants')['data'] ?? [];
        }

        $sizes = array_values(array_filter((array) ($data['sizes'] ?? [])));
        // Tag colours plus the names of the image grid's options.
        $colors = array_values(array_unique(array_merge(
            array_filter((array) ($data['colors'] ?? [])),
            array_filter(array_map(fn ($o) => is_array($o) ? ($o['name'] ?? null) : null, (array) ($data['options'] ?? []))),
        )));

        $hasBlock = $data !== [];

        return [
            'size_enabled' => $hasBlock && (bool) ($data['size_enabled'] ?? $sizes !== []),
            'size_options' => $sizes,
            'size_label' => $data['size_label'] ?? 'সাইজ নির্বাচন করুন',
            'size_required' => (bool) ($data['size_required'] ?? true),
            'color_enabled' => $hasBlock && (bool) ($data['color_enabled'] ?? $colors !== []),
            'color_options' => $colors,
            'color_label' => $data['color_label'] ?? 'কালার নির্বাচন করুন',
            'color_required' => (bool) ($data['color_required'] ?? false),
        ];
    }

    /**
     * What the package cards / summary show: the page's packages, or — when it
     * has none — one card for the linked product. Prices are the page's own.
     *
     * @return list<array{label: string, image: ?string, price: float, compare_price: ?float, quantity: int}>
     */
    private function cards(LandingPage $page, array $packages): array
    {
        if ($packages) {
            return array_map(fn ($p) => [
                'label' => (string) ($p['label'] ?? ''),
                'image' => $p['image'] ?? null,
                'price' => (float) $p['price'],
                'compare_price' => is_numeric($p['compare_price'] ?? null) ? (float) $p['compare_price'] : null,
                'quantity' => max(1, (int) ($p['quantity'] ?? 1)),
            ], $packages);
        }

        if (! $page->product) {
            return [];
        }

        $price = $page->effectivePrice();
        $regular = (float) $page->product->price;

        return [[
            'label' => (string) $page->product->name,
            'image' => $page->product->image ?: null,
            'price' => $price,
            'compare_price' => $regular > $price ? $regular : null,
            'quantity' => 1,
        ]];
    }

    /** The chosen package, or the first one when the index is out of range. */
    private function selectedPackage(array $packages): ?array
    {
        return $packages[$this->packageIndex] ?? ($packages[0] ?? null);
    }

    private function districtForZone(LandingPage $page): string
    {
        // Legacy compact form: only distinguish Dhaka when the charges differ.
        if (! $this->isBlockPage($page) && Delivery::isUniform()) {
            return 'N/A';
        }

        return $this->zone === 'dhaka' ? Delivery::DHAKA : 'ঢাকার বাইরে';
    }

    // Incomplete-order capture (see CheckoutForm): a valid phone typed on blur.
    public function updatedPhone(): void
    {
        try {
            $page = $this->page();
            $packages = $this->packages($page);
            $package = $this->selectedPackage($packages);

            if ($package) {
                $quantity = max(1, (int) ($package['quantity'] ?? 1)) * $this->quantity;
                $item = [
                    'product_id' => (int) $package['product_id'],
                    'variant_id' => null,
                    'name' => (string) ($package['label'] ?? ''),
                    'quantity' => $quantity,
                    'unit_price' => (float) $package['price'] * $this->quantity / $quantity,
                ];
            } elseif ($page->product) {
                $item = [
                    'product_id' => $page->product_id,
                    'variant_id' => null,
                    'name' => $page->product->name,
                    'quantity' => $this->quantity,
                    'unit_price' => $page->effectivePrice(),
                ];
            } else {
                return;
            }

            IncompleteOrder::capture(session()->getId(), 'landing', $page->id, $this->phone, $this->customer_name, null, $this->address, [$item], request()->ip());
        } catch (Throwable) {
            // Never get in the way of ordering.
        }
    }

    public function increment(): void
    {
        $this->quantity = min(20, $this->quantity + 1);
    }

    public function decrement(): void
    {
        $this->quantity = max(1, $this->quantity - 1);
    }

    public function selectPackage(int $index): void
    {
        if ($index !== $this->packageIndex) {
            $this->packageIndex = $index;
            $this->quantity = 1;
        }
    }

    /** Fired by an option card's order button in the variants grid (browser event). */
    #[On('select-variant')]
    public function selectVariant(string $color = ''): void
    {
        if (in_array($color, $this->variantConfig($this->page())['color_options'], true)) {
            $this->color = $color;
        }
    }

    public function placeOrder(OrderService $orderService, BkashService $bkash)
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

        $isBkash = $this->payment_method === 'bkash';

        if ($isBkash && ! $bkash->configured()) {
            $this->error = 'বিকাশ পেমেন্ট এই মুহূর্তে চালু নেই। অনুগ্রহ করে ক্যাশ অন ডেলিভারি বেছে নিন।';

            return null;
        }

        $packages = $this->packages($landingPage);
        $package = $this->selectedPackage($packages);

        if ($package) {
            $productId = (int) $package['product_id'];
            $quantity = max(1, (int) ($package['quantity'] ?? 1)) * $this->quantity;
            // The package price is the total for the package; OrderService
            // multiplies a per-unit price by quantity, so hand it total ÷ quantity.
            $unitPrice = (float) $package['price'] * $this->quantity / $quantity;
            $packageNote = ' | প্যাকেজ: '.($package['label'] ?? '').($this->quantity > 1 ? " × {$this->quantity}" : '');
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
                'district' => $this->districtForZone($landingPage),
                'thana' => 'N/A',
                'postcode' => null,
                'address' => $this->address,
                'notes' => 'Landing page: '.$landingPage->title.$packageNote.$variantNote,
                'ip_address' => request()->ip(),
                'payment_method' => $this->payment_method,
            ], null, $landingPage->id, reserveStock: ! $isBkash);
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        if ($isBkash) {
            try {
                // Amount sent to bKash is $order->total, computed server-side.
                $payment = $bkash->createPayment($order);
            } catch (RuntimeException $e) {
                $orderService->markBkashFailed($order, $e->getMessage());
                $this->error = 'বিকাশ পেমেন্ট শুরু করা যায়নি। অনুগ্রহ করে আবার চেষ্টা করুন অথবা ক্যাশ অন ডেলিভারি বেছে নিন।';

                return null;
            }

            return redirect()->away($payment['bkashURL']);
        }

        try {
            IncompleteOrder::markConverted($this->phone, $order->id);
        } catch (Throwable) {
        }

        return redirect()->route('order.show', $order->order_token);
    }

    public function render(BkashService $bkash)
    {
        $page = $this->page();
        $packages = $this->packages($page);
        $block = $this->isBlockPage($page);
        $cards = $this->cards($page, $packages);
        $card = $cards[$this->packageIndex] ?? ($cards[0] ?? null);

        $subtotal = $card ? $card['price'] * max(1, $this->quantity) : 0.0;

        // Legacy compact form keeps its original single-price display.
        if (! $block) {
            $subtotal = $page->effectivePrice() * max(1, $this->quantity);
        }

        $zoneFee = fn (string $zone): float => Delivery::fee($zone === 'dhaka' ? Delivery::DHAKA : 'ঢাকার বাইরে', $subtotal);

        $district = $block || ! Delivery::isUniform() ? ($this->zone === 'dhaka' ? Delivery::DHAKA : 'ঢাকার বাইরে') : null;

        $data = [
            'packages' => $packages,
            'package' => $this->selectedPackage($packages),
            'cards' => $cards,
            'card' => $card,
            'variants' => $this->variantConfig($page),
            'subtotal' => $subtotal,
            'showSummary' => $subtotal > 0,
            'zoneChoice' => $block ? true : (! Delivery::isUniform() && ! Delivery::qualifiesForFree($subtotal)),
            'shippingFee' => Delivery::fee($district, $subtotal),
            'dhakaFee' => $zoneFee('dhaka'),
            'outsideFee' => $zoneFee('outside'),
            'bkashAvailable' => $bkash->configured(),
        ];

        return view($block ? 'livewire.landing.landing-order-form' : 'livewire.landing.landing-order-form-simple', $data);
    }
}
