<?php

namespace App\Http\Controllers;

use App\Models\Order;

class InvoiceController extends Controller
{
    /**
     * Printable invoice, reachable ONLY by the order's unguessable
     * order_token (same access model as the order-confirmation page —
     * nobody can enumerate someone else's invoice by guessing an id).
     * The admin panel links here using the token it already has for the
     * order, so this one secured route serves both admin and customer.
     */
    public function show(string $token)
    {
        $order = Order::with('items')->where('order_token', $token)->firstOrFail();

        return view('invoice.show', ['order' => $order]);
    }
}
