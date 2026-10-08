<?php

namespace App\Livewire\Cart;

use App\Services\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

class CartBadge extends Component
{
    public bool $floating = false;

    /** Mobile bottom navigation: sits on the cart icon's top-right corner. */
    public bool $nav = false;

    /** Header cart button (round brand icon button). */
    public bool $header = false;

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
