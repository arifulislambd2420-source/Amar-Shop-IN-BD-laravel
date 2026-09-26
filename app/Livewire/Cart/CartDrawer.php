<?php

namespace App\Livewire\Cart;

use App\Services\CartService;
use App\Services\OrderService;
use Livewire\Attributes\On;
use Livewire\Component;

class CartDrawer extends Component
{
    #[On('cart-updated')]
    public function refresh(): void
    {
        // no-op — Livewire re-renders this component automatically
    }

    public function updateQuantity(int $productId, ?int $variantId, int $quantity): void
    {
        app(CartService::class)->updateQuantity($productId, $variantId, $quantity);
        $this->dispatch('cart-updated');
    }

    public function remove(int $productId, ?int $variantId): void
    {
        app(CartService::class)->remove($productId, $variantId);
        $this->dispatch('cart-updated');
    }

    public function render(CartService $cart)
    {
        return view('livewire.cart.cart-drawer', [
            'lines' => $cart->lines(),
            'subtotal' => $cart->subtotal(),
            'shippingFee' => OrderService::SHIPPING_FEE,
        ]);
    }
}
