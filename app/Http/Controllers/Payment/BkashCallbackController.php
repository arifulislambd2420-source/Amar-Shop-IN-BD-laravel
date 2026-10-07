<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\Payment\BkashService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * bKash redirects the customer's browser here after they finish (or
 * abandon) the hosted payment page, with ?paymentID=...&status=success|
 * failure|cancel in the query string.
 *
 * Security: the "status" query parameter is never trusted to decide
 * anything by itself — anyone could craft that URL. The only source of
 * truth is BkashService::executePayment(), a server-to-server call made
 * with our own app_key/token, matching Invariant-style rules already used
 * elsewhere in this app (order lookup only by unguessable token, phone
 * always required for tracking, etc.).
 */
class BkashCallbackController extends Controller
{
    public function callback(Request $request, BkashService $bkash, OrderService $orderService, CartService $cart)
    {
        $paymentId = (string) $request->query('paymentID', '');

        abort_if($paymentId === '', 400, 'Missing paymentID.');

        $order = $orderService->findPendingBkashOrder($paymentId);

        if (! $order) {
            // Not a still-pending order of ours — either an unknown/forged
            // paymentID, or this callback was already handled once before
            // (e.g. the customer pressed back then forward). Look it up
            // without the "unpaid" restriction purely to redirect them
            // somewhere sensible; no payment action is taken either way.
            $order = $orderService->findByTransactionId($paymentId);

            if (! $order) {
                abort(404);
            }

            return $order->payment_status === 'paid'
                ? redirect()->route('order.show', $order->order_token)
                : $this->retryRedirect($order)->with('status', 'এই পেমেন্টটি আগেই ব্যর্থ হিসেবে চিহ্নিত হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।');
        }

        try {
            $result = $bkash->executePayment($paymentId);
        } catch (RuntimeException $e) {
            $orderService->markBkashFailed($order, $e->getMessage());

            return $this->retryRedirect($order)
                ->with('status', 'বিকাশ পেমেন্ট যাচাই করা যায়নি। অনুগ্রহ করে আবার চেষ্টা করুন।');
        }

        $transactionStatus = $result['transactionStatus'] ?? null;
        $trxId = $result['trxID'] ?? null;

        if ($transactionStatus === 'Completed' && filled($trxId)) {
            $orderService->finalizeBkashPayment($order, $trxId);

            $cart->clear();

            return redirect()->route('order.show', $order->order_token);
        }

        $orderService->markBkashFailed($order, $result['statusMessage'] ?? $transactionStatus ?? 'unknown');

        return $this->retryRedirect($order)
            ->with('status', 'বিকাশ পেমেন্ট সম্পন্ন হয়নি (ব্যর্থ অথবা বাতিল করা হয়েছে)। অনুগ্রহ করে আবার চেষ্টা করুন।');
    }

    /**
     * Where a customer goes after a failed/cancelled bKash payment: back to
     * the landing page they ordered from, else the checkout.
     */
    private function retryRedirect(Order $order)
    {
        $slug = $order->landing_page_id ? \App\Models\LandingPage::whereKey($order->landing_page_id)->value('slug') : null;

        return $slug ? redirect()->route('landing.show', $slug) : redirect()->route('checkout');
    }
}
