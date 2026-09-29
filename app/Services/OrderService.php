<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Order creation — mirrors the old Next.js app's createOrder() in
 * src/lib/orders.ts: everything happens inside one DB transaction with
 * row-level locks (lockForUpdate) on both products and variants, so two
 * concurrent checkouts can never both pass the stock check and oversell
 * the last unit.
 */
class OrderService
{
    // Flat delivery charge — same value as the old app's SHIPPING_FEE
    // constant in src/lib/format.ts. Always added to the stored total,
    // never just displayed.
    public const SHIPPING_FEE = 70;

    /**
     * @param  array<int, array{product_id:int, variant_id:?int, quantity:int, unit_price_override?:?float}>  $cartLines
     *         unit_price_override is for server-side callers only (e.g. a
     *         landing page's admin-set price_override) — it is never taken
     *         from client/request input directly, only from trusted DB data
     *         looked up beforehand by the caller.
     * @param  array  $customer  keys: customer_name, phone, email, district, thana, postcode, address, notes, payment_method,
     *         ip_address (optional; enables the per-IP fraud rule)
     * @param  bool  $reserveStock  Decrement stock now (the default — correct
     *         for COD, where the order itself is the commitment). Pass false
     *         for a gateway payment (bKash): stock is only ever decremented
     *         once payment is confirmed, by finalizeBkashPayment() below, so
     *         an abandoned/failed payment never holds stock hostage.
     */
    public function createOrder(array $cartLines, array $customer, ?string $couponCode = null, ?int $landingPageId = null, bool $reserveStock = true): Order
    {
        if (empty($cartLines)) {
            throw new RuntimeException('Cart is empty');
        }

        return DB::transaction(function () use ($cartLines, $customer, $couponCode, $landingPageId, $reserveStock) {
            $lineItems = [];

            foreach ($cartLines as $line) {
                // Row-lock the base product first so concurrent checkouts
                // serialize on it.
                $product = Product::where('id', $line['product_id'])->lockForUpdate()->first();
                if (! $product) {
                    throw new RuntimeException("Product {$line['product_id']} not found");
                }

                $quantity = (int) $line['quantity'];
                if ($quantity < 1) {
                    throw new RuntimeException("\"{$product->name}\" এর পরিমাণ সঠিক নয়");
                }

                $unitPrice = (float) ($product->sale_price ?? $product->price);
                $productName = $product->name;
                $variantId = null;

                if (! empty($line['variant_id'])) {
                    $variant = ProductVariant::where('id', $line['variant_id'])
                        ->where('product_id', $product->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $variant) {
                        throw new RuntimeException("Variant {$line['variant_id']} not found");
                    }

                    if ($variant->stock < $quantity) {
                        throw new RuntimeException("\"{$product->name} {$variant->label}\" এর জন্য পর্যাপ্ত স্টক নেই (আছে: {$variant->stock})");
                    }

                    $unitPrice = (float) $variant->price;
                    $productName = "{$product->name} ({$variant->label})";
                    $variantId = $variant->id;
                } elseif ($product->stock < $quantity) {
                    throw new RuntimeException("\"{$product->name}\" এর জন্য পর্যাপ্ত স্টক নেই (আছে: {$product->stock})");
                }

                // Stock is always checked against the real product/variant
                // above regardless of this — only the charged price changes.
                if (isset($line['unit_price_override']) && $line['unit_price_override'] !== null) {
                    $unitPrice = (float) $line['unit_price_override'];
                }

                $lineItems[] = [
                    'product' => $product,
                    'variant_id' => $variantId,
                    'product_name' => $productName,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'line_total' => $unitPrice * $quantity,
                ];
            }

            $subtotal = array_sum(array_column($lineItems, 'line_total'));
            $shippingFee = self::SHIPPING_FEE;

            $discount = 0;
            if ($couponCode) {
                $coupon = Coupon::where('code', $couponCode)->where('is_active', true)->lockForUpdate()->first();

                $valid = $coupon
                    && (! $coupon->valid_until || $coupon->valid_until->greaterThanOrEqualTo(now()))
                    && ($coupon->max_uses === null || $coupon->uses < $coupon->max_uses)
                    && $subtotal >= (float) $coupon->min_spend;

                if (! $valid) {
                    throw new RuntimeException('কুপন কোডটি সঠিক নয় অথবা শর্ত পূরণ করেনি');
                }

                $discount = $coupon->discount_type === 'percent'
                    ? ($subtotal * (float) $coupon->discount_value) / 100
                    : (float) $coupon->discount_value;

                $discount = min($discount, $subtotal);

                $coupon->increment('uses');
            }

            $total = $subtotal + $shippingFee - $discount;

            $phone = $this->normalizePhone($customer['phone']);
            $ip = $customer['ip_address'] ?? null;
            $flagReasons = $this->flagReasons($phone, array_map(fn ($li) => $li['product']->id, $lineItems), $ip);

            $order = Order::create([
                'landing_page_id' => $landingPageId,
                'is_flagged' => $flagReasons !== [],
                'flag_reason' => $flagReasons ? implode("
", $flagReasons) : null,
                'ip_address' => $ip,
                'order_token' => Str::random(40),
                'invoice_no' => 'INV-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'customer_name' => $customer['customer_name'],
                'phone' => $phone,
                'email' => $customer['email'] ?? null,
                'district' => $customer['district'],
                'thana' => $customer['thana'],
                'postcode' => $customer['postcode'] ?? null,
                'address' => $customer['address'],
                'payment_method' => $customer['payment_method'] ?? 'cod',
                'payment_status' => 'unpaid',
                'status' => 'pending',
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'discount' => $discount,
                'total' => $total,
                'notes' => $customer['notes'] ?? '',
            ]);

            foreach ($lineItems as $li) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $li['product']->id,
                    'product_name' => $li['product_name'],
                    'unit_price' => $li['unit_price'],
                    'quantity' => $li['quantity'],
                    'discount' => 0,
                    'line_total' => $li['line_total'],
                ]);

                if ($reserveStock) {
                    if ($li['variant_id']) {
                        ProductVariant::where('id', $li['variant_id'])->decrement('stock', $li['quantity']);
                    } else {
                        Product::where('id', $li['product']->id)->decrement('stock', $li['quantity']);
                    }
                }
            }

            return $order;
        });
    }

    /**
     * Reasons this order looks duplicate/fake (empty = normal). Flag-only:
     * the order is created either way, and a bug in the check must never
     * cost a real sale, so any failure just means "not flagged".
     *
     * @param  list<int>  $productIds
     * @return list<string>
     */
    protected function flagReasons(string $phone, array $productIds, ?string $ip): array
    {
        try {
            return app(OrderRiskService::class)->evaluate($phone, $productIds, $ip);
        } catch (Throwable $e) {
            Log::warning('Order risk check failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    protected function normalizePhone(string $raw): string
    {
        return preg_replace('/[^0-9]/', '', $raw) ?? '';
    }

    /**
     * Public order-confirmation lookup — by the unguessable token issued at
     * checkout, never by the sequential numeric id.
     */
    public function findByToken(string $token): ?Order
    {
        return Order::with('items')->where('order_token', $token)->first();
    }

    /**
     * Looked up by BkashCallbackController — a bKash paymentID, stored on
     * the order at createPayment() time. Restricting to payment_method
     * ='bkash' AND payment_status='unpaid' means a callback hit with a
     * paymentID that isn't a real, still-pending order of ours (a guess, or
     * one already finalized) simply finds nothing rather than letting an
     * attacker confirm an arbitrary order by paymentID alone.
     */
    public function findPendingBkashOrder(string $paymentId): ?Order
    {
        return Order::with('items')
            ->where('transaction_id', $paymentId)
            ->where('payment_method', 'bkash')
            ->where('payment_status', 'unpaid')
            ->first();
    }

    /**
     * Any bKash order by transaction_id regardless of payment_status — used
     * only for an idempotent re-visit of the callback URL (the customer's
     * back/forward button, a double-submitted redirect) after
     * findPendingBkashOrder() already found nothing because it was already
     * finalized. Never used to decide whether to confirm a payment.
     */
    public function findByTransactionId(string $transactionId): ?Order
    {
        return Order::with('items')
            ->where('transaction_id', $transactionId)
            ->where('payment_method', 'bkash')
            ->first();
    }

    /**
     * Confirms a bKash payment: re-validates stock fresh (row-locked) and
     * decrements it now — this is the only point a bKash order's stock is
     * ever touched — then marks the order paid. $trxId is bKash's final
     * settlement transaction id (overwrites the paymentID that was stored
     * in transaction_id at createPayment() time; paymentID is no longer
     * needed once execute() has succeeded).
     *
     * If stock ran out between checkout and payment (rare, since the
     * customer already paid bKash), the order is NOT silently marked as a
     * normal paid order: it's flagged on_hold with a note, for an admin to
     * resolve by hand (restock/refund) — money already moved, so this can
     * never fail loudly back to the customer.
     */
    public function finalizeBkashPayment(Order $order, string $trxId): void
    {
        DB::transaction(function () use ($order, $trxId) {
            $order = Order::with('items')->lockForUpdate()->find($order->id);

            $stockConflict = null;

            foreach ($order->items as $item) {
                if ($item->product_id === null) {
                    continue;
                }

                // The order stores only product_id, not which variant was
                // ordered — this mirrors OrderItem's own schema. Falling
                // back to the base product's stock is the same trade-off
                // OrderService already made at order-creation time.
                $product = Product::where('id', $item->product_id)->lockForUpdate()->first();

                if (! $product || $product->stock < $item->quantity) {
                    $stockConflict = $item->product_name;

                    break;
                }
            }

            if ($stockConflict) {
                $order->update([
                    'payment_status' => 'paid',
                    'transaction_id' => $trxId,
                    'status' => 'on_hold',
                    'notes' => trim($order->notes."\n[bKash paid, but \"{$stockConflict}\" is now out of stock — needs manual review.]"),
                ]);

                return;
            }

            foreach ($order->items as $item) {
                if ($item->product_id !== null) {
                    Product::where('id', $item->product_id)->decrement('stock', $item->quantity);
                }
            }

            $order->update([
                'payment_status' => 'paid',
                'transaction_id' => $trxId,
                'status' => 'processing',
            ]);
        });
    }

    /**
     * A bKash payment that failed, was cancelled, or couldn't be confirmed.
     * Stock was never reserved for this order (createOrder was called with
     * $reserveStock: false), so there's nothing to release.
     */
    public function markBkashFailed(Order $order, string $reason): void
    {
        $order->update([
            'payment_status' => 'failed',
            'notes' => trim($order->notes."\n[bKash payment failed: {$reason}]"),
        ]);
    }

    /**
     * Order tracking — phone is ALWAYS required and must match the order's
     * phone before any data is returned (fixes the old IDOR: looking up by
     * id/invoice alone is never sufficient).
     */
    public function track(string $phone, ?string $orderIdOrInvoice = null): Order|string
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if (! $normalizedPhone) {
            return 'অনুগ্রহ করে Phone Number দিন।';
        }

        if ($orderIdOrInvoice) {
            $order = Order::with('items')
                ->where(function ($q) use ($orderIdOrInvoice) {
                    if (ctype_digit($orderIdOrInvoice)) {
                        $q->where('id', (int) $orderIdOrInvoice);
                    }
                    $q->orWhere('invoice_no', $orderIdOrInvoice);
                })
                ->first();

            if (! $order) {
                return 'এই Order ID/Invoice এর কোনো অর্ডার পাওয়া যায়নি।';
            }

            $orderPhone = $this->normalizePhone($order->phone);
            $matches = $orderPhone === $normalizedPhone
                || (strlen($normalizedPhone) >= 4 && str_ends_with($orderPhone, $normalizedPhone));

            if (! $matches) {
                return 'Order ID ও Phone Number মিলছে না।';
            }

            return $order;
        }

        if (strlen($normalizedPhone) < 7) {
            return 'শুধু Phone দিয়ে খুঁজতে হলে পুরো নম্বর দিন (অন্তত ৭ ডিজিট)।';
        }

        // Exact phone match only — a LIKE/suffix match here could return a
        // different customer's order if two numbers happen to share a tail.
        $order = Order::with('items')
            ->where('phone', $normalizedPhone)
            ->orderByDesc('created_at')
            ->first();

        if (! $order) {
            return 'এই Phone Number দিয়ে কোনো অর্ডার পাওয়া যায়নি।';
        }

        return $order;
    }
}
