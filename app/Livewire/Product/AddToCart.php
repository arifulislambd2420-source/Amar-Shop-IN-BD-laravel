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

    public function mount(int $productId, string $mode = 'card', bool $iconOnly = false): void
    {
        $this->productId = $productId;
        $this->mode = $mode;
        $this->iconOnly = $iconOnly;

        $product = Product::with('variants')->find($productId);
        if ($product && $product->variants->isNotEmpty()) {
            $this->variantId = $product->variants->first()->id;
        }
    }

    public function add(): void
    {
        app(CartService::class)->add($this->productId, max(1, $this->quantity), $this->variantId);
        $this->dispatch('cart-updated');
        $this->message = 'কার্টে যোগ করা হয়েছে!';
    }

    public function buyNow()
    {
        app(CartService::class)->add($this->productId, max(1, $this->quantity), $this->variantId);
        $this->dispatch('cart-updated');

        return redirect()->route('checkout');
    }

    public function render()
    {
        $product = Product::with('variants')->find($this->productId);

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
