<?php

namespace App\Livewire\Product;

use App\Models\Product;
use App\Services\CartService;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Reused on both the ProductCard (mode=card, iconOnly quick add) and the
 * product detail page (mode=detail, full variant selector + qty + buy now).
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
     * product + variants — that was 4 queries per card (28 on the
     * homepage). Protected, so Livewire doesn't persist it: later requests
     * (add/buyNow) just fall back to a fresh query.
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
            $this->variantId = $product->variants->first()->id;
        }
    }

    public function add(): void
    {
        app(CartService::class)->add($this->productId, max(1, $this->quantity), $this->variantId);
        $this->dispatch('cart-updated');
        $this->dispatch('gtm:add_to_cart', ecommerce: $this->addToCartPayload());
        $this->message = 'কার্টে যোগ করা হয়েছে!';
    }

    /**
     * GTM add_to_cart ecommerce payload — same shape as the old app's
     * AddToCartButton.tsx: currency BDT, value = price * quantity, one
     * items[] entry with item_id/item_name/price/quantity.
     */
    protected function addToCartPayload(): array
    {
        $product = Product::with('variants')->find($this->productId);
        $variant = $this->variantId ? $product?->variants->firstWhere('id', $this->variantId) : null;
        $quantity = max(1, $this->quantity);
        $price = $variant ? (float) $variant->price : (float) ($product?->sale_price ?? $product?->price ?? 0);

        return [
            'currency' => 'BDT',
            'value' => $price * $quantity,
            'items' => [[
                'item_id' => $this->productId,
                'item_name' => $product?->name,
                'price' => $price,
                'quantity' => $quantity,
            ]],
        ];
    }

    public function buyNow()
    {
        app(CartService::class)->add($this->productId, max(1, $this->quantity), $this->variantId);
        $this->dispatch('cart-updated');

        return redirect()->route('checkout');
    }

    public function render()
    {
        $product = $this->preloaded ?? Product::with('variants')->find($this->productId);

        return view('livewire.product.add-to-cart', [
            'product' => $product,
            'outOfStock' => $product
                ? ($this->variantId
                    ? optional($product->variants->firstWhere('id', $this->variantId))->stock <= 0
                    : $product->stock <= 0)
                : true,
        ]);
    }
}
