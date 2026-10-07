<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use App\Support\Tracking;

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

        // Purchase tracking: built from the stored order, fired once per
        // browser session, only for a real (counted) order placed recently —
        // so reloading the page or opening an old link never double-counts.
        $purchase = null;
        $sessionKey = 'purchase_tracked.'.$order->id;

        if (Tracking::enabled()
            && ! session()->has($sessionKey)
            && $order->created_at->gt(now()->subDay())
            && \App\Models\Order::counted()->whereKey($order->id)->exists()) {
            $purchase = Tracking::purchase($order);
            session()->put($sessionKey, true);
        }

        return view('order.show', compact('order', 'purchase'));
    }
}
