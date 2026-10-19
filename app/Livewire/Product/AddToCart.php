<?php

namespace App\Livewire\Product;

use App\Models\Product;
use App\Services\CartService;
use App\Support\Tracking;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Reused on both the ProductCard (mode=card) and the product detail page
 * (mode=detail: size/variant chips, quantity stepper, add / buy now, and a
 * sticky buy bar on phones).
 *
 * On a card, a product with more than one variant never adds a size the
 * customer did not pick: its buttons lead to the product page instead.
 */
class AddToCart extends Component
{
    #[Locked]
    public int $productId;

    public string $mode = 'card';

    public bool $iconOnly = false;

    public ?int $variantId = null;

    public int $quantity = 1;

    public string $message = '';

    /**
     * The already-loaded product from the parent (e.g. a product card in a
     * grid), reused for the first render so each card doesn't re-query its
     * product + variants. Protected, so Livewire doesn't persist it: later
     * requests (add/buyNow) just fall back to a fresh query.
     */
    protected ?Product $preloaded = null;

    public function mount(int $productId, string $mode = 'card', bool $iconOnly = false, ?Product $product = null): void
    {
        $this->productId = $productId;
        $this->mode = $mode;
        $this->iconOnly = $iconOnly;

        $product = $product && $product->id === $productId
            ? $product->loadMissing('variants')
            : Product::with('variants')->find($productId);

        $this->preloaded = $product;

        if ($product && $product->variants->isNotEmpty()) {
            // Preselect the first size that can actually be bought.
            $this->variantId = $product->defaultVariant()->id;
        }
    }

    public function selectVariant(int $variantId): void
    {
        $product = $this->product();

        if ($product?->variants->contains('id', $variantId)) {
            $this->variantId = $variantId;
            $this->message = '';

            // The gallery jumps to this colour's picture (if it has one).
            if ($image = $product->variants->firstWhere('id', $variantId)->image) {
                $this->dispatch('variant-image', url: $image);
            }
            $this->quantity = max(1, min($this->quantity, $this->maxQuantity($product)));

            // ViewContent again, for the colour / size now chosen (product page only).
            if ($this->mode === 'detail' && Tracking::enabled()) {
                $variant = $this->selectedVariant($product);
                $this->dispatch('gtm:view_item', ecommerce: Tracking::product($product->loadMissing('category'), (float) $variant->price, 1, $variant));
            }
        }
    }

    /**
     * Pick a colour: switch to its first buyable size (keeping the current
     * size when that colour has it) and show that colour's picture.
     */
    public function selectColor(string $color): void
    {
        $product = $this->product();
        $current = $this->selectedVariant($product);
        $options = $product?->variants->filter(fn ($v) => (string) $v->color === $color);

        if (! $options || $options->isEmpty()) {
            return;
        }

        $pick = ($current && $current->size !== null
                ? $options->first(fn ($v) => $v->size === $current->size && $v->stock > 0)
                : null)
            ?? $options->first(fn ($v) => $v->stock > 0)
            ?? $options->first();

        $this->selectVariant($pick->id);
    }

    public function increment(): void
    {
        $this->quantity = min($this->quantity + 1, $this->maxQuantity($this->product()));
    }

    public function decrement(): void
    {
        $this->quantity = max(1, $this->quantity - 1);
    }

    public function add(): void
    {
        if (! $this->canBuy()) {
            return;
        }

        app(CartService::class)->add($this->productId, $this->cleanQuantity(), $this->variantId);
        $this->dispatch('cart-updated');
        $this->dispatch('gtm:add_to_cart', ecommerce: $this->addToCartPayload());
        $this->message = 'কার্টে যোগ করা হয়েছে!';
    }

    public function buyNow()
    {
        if (! $this->canBuy()) {
            return null;
        }

        app(CartService::class)->add($this->productId, $this->cleanQuantity(), $this->variantId);
        $this->dispatch('cart-updated');
        // "Order now" puts the item in the cart too, so it counts as AddToCart.
        $this->dispatch('gtm:add_to_cart', ecommerce: $this->addToCartPayload());

        return redirect()->route('checkout');
    }

    /**
     * GTM add_to_cart ecommerce payload — same shape as the old app's
     * AddToCartButton.tsx: currency BDT, value = price * quantity, one
     * items[] entry with item_id/item_name/price/quantity.
     */
    protected function addToCartPayload(): array
    {
        $product = $this->product();
        $variant = $this->selectedVariant($product);
        $quantity = $this->cleanQuantity();
        $price = $variant ? (float) $variant->price : (float) ($product?->sale_price ?? $product?->price ?? 0);

        // Product (variant) as item_name, the variant in item_variant, plus content_name / content_category.
        return Tracking::product($product->loadMissing('category'), $price, $quantity, $variant);
    }

    protected function product(): ?Product
    {
        return $this->preloaded ??= Product::with('variants')->find($this->productId);
    }

    protected function selectedVariant(?Product $product)
    {
        return $this->variantId ? $product?->variants->firstWhere('id', $this->variantId) : null;
    }

    /** Units of the current choice that are in stock (0 = sold out). */
    protected function availableStock(?Product $product): int
    {
        if (! $product) {
            return 0;
        }

        $variant = $this->selectedVariant($product);

        return max(0, (int) ($variant ? $variant->stock : $product->stock));
    }

    protected function maxQuantity(?Product $product): int
    {
        return max(1, $this->availableStock($product));
    }

    protected function cleanQuantity(): int
    {
        return max(1, min($this->quantity, $this->maxQuantity($this->product())));
    }

    /** A card can't buy a product whose size has to be chosen first. */
    protected function canBuy(): bool
    {
        $product = $this->product();

        if (! $product || $this->availableStock($product) <= 0) {
            return false;
        }

        return ! ($this->mode === 'card' && $product->variants->count() > 1);
    }

    public function render()
    {
        $product = $this->product();
        $variant = $this->selectedVariant($product);
        $stock = $this->availableStock($product);

        return view('livewire.product.add-to-cart', [
            'product' => $product,
            'variant' => $variant,
            'stock' => $stock,
            'outOfStock' => $stock <= 0,
            'soldOut' => ! $product || $product->isSoldOut(),
            'needsChoice' => $product && $product->variants->count() > 1,
            'unitPrice' => $variant ? (float) $variant->price : ($product ? $product->displayPrice() : 0),
            'lowStock' => $stock > 0 && $stock <= Product::lowStockThreshold(),
        ]);
    }
}
