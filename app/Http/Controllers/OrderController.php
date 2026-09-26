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

        return view('order.show', compact('order'));
    }
}
