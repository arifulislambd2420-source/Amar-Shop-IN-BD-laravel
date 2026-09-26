<?php

namespace App\Http\Controllers;

use App\Services\CartService;

class CheckoutController extends Controller
{
    public function show(CartService $cart)
    {
        if (empty($cart->lines())) {
            return redirect()->route('shop')
                ->with('status', 'আপনার কার্টটি এখন খালি — checkout করার আগে পণ্য যোগ করুন।');
        }

        return view('checkout.index');
    }
}
