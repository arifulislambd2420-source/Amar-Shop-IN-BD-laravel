<?php

namespace App\Livewire\Cart;

use App\Services\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

class CartBadge extends Component
{
    public bool $floating = false;

    #[On('cart-updated')]
    public function refresh(): void
    {
        // no-op — re-rendering happens automatically on the event
    }

    public function render(CartService $cart)
    {
        return view('livewire.cart.cart-badge', [
            'count' => $cart->count(),
        ]);
    }
}
