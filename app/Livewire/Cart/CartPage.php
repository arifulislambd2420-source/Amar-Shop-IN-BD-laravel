<?php

namespace App\Livewire\Cart;

use App\Services\CartService;
use App\Support\Delivery;
use Livewire\Component;

class CartPage extends Component
{
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
        return view('livewire.cart.cart-page', [
            'lines' => $cart->lines(),
            'subtotal' => $cart->subtotal(),
            'shippingFee' => Delivery::quote(null, $cart->subtotal()),
            'freeRemaining' => Delivery::remainingForFree($cart->subtotal()),
        ]);
    }
}
