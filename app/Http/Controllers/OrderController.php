<?php

namespace App\Http\Controllers;

use App\Services\OrderService;

class OrderController extends Controller
{
    /**
     * Invariant #2: looked up ONLY by the random order_token, never the
     * sequential numeric id.
     */
    public function show(string $token, OrderService $orderService)
    {
        $order = $orderService->findByToken($token);

        abort_if(! $order, 404);

        // No Purchase from the browser: it is sent by the server when the order is confirmed
        // (App\Services\Tracking\ServerPurchase). The browser sends OrderPlaced when the order is made.
        return view('order.show', compact('order'));
    }
}
