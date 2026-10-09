<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Delivery;
use App\Support\SiteSettingsHelper;
use App\Support\Stock;
use Illuminate\Support\Facades\Cache;
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
    /**
     * @param  array<int, array{product_id:int, variant_id:?int, quantity:int, unit_price_override?:?float}>  $cartLines
     *                                                                                                                    unit_price_override is for server-side callers only (e.g. a
     *                                                                                                                    landing page's admin-set price_override) — it is never taken
     *                                                                                                                    from client/request input directly, only from trusted DB data
     *                                                                                                                    looked up beforehand by the caller.
     * @param  array  $customer  keys: customer_name, phone, email, district, thana, postcode, address, notes, payment_method,
     *                           ip_address (optional; enables the per-IP fraud rule)
     * @param  bool  $reserveStock  Decrement stock now (the default — correct
     *                              for COD, where the order itself is the commitment). Pass false
     *                              for a gateway payment (bKash): stock is only ever decremented
     *                              once payment is confirmed, by finalizeBkashPayment() below, so
     *                              an abandoned/failed payment never holds stock hostage.
     */
    public function createOrder(array $cartLines, array $customer, ?string $couponCode = null, ?int $landingPageId = null, bool $reserveStock = true, bool $bypassCooldown = false): Order
    {
        if (empty($cartLines)) {
            throw new RuntimeException('Cart is empty');
        }

        $minutes = $bypassCooldown ? 0 : self::cooldownMinutes();

        if ($minutes <= 0) {
            return $this->persistOrder($cartLines, $customer, $couponCode, $landingPageId, $reserveStock);
        }

        // Same phone ordering again within the cooldown is refused. The lock
        // makes two simultaneous submissions from one phone serialize, so the
        // second one sees the first order.
        $phone = $this->normalizePhone((string) ($customer['phone'] ?? ''));
        $lock = Cache::lock('order-phone:'.substr($phone, -10), 15);

        if (! $lock->get()) {
            throw new RuntimeException('আপনার অর্ডারটি প্রসেস হচ্ছে। অনুগ্রহ করে একটু অপেক্ষা করুন।');
        }

        try {
            $recent = Order::counted()
                ->whereIn('phone', app(OrderRiskService::class)->phoneVariants($phone))
                ->where('created_at', '>=', now()->subMinutes($minutes))
                ->latest('created_at')
                ->first();

            if ($recent) {
                $wait = max(1, $minutes - (int) $recent->created_at->diffInMinutes(now()));

                throw new RuntimeException("এই ফোন নম্বর থেকে সম্প্রতি একটি অর্ডার করা হয়েছে। অনুগ্রহ করে {$wait} মিনিট পর আবার চেষ্টা করুন, অথবা আমাদের কল করুন।");
            }

            return $this->persistOrder($cartLines, $customer, $couponCode, $landingPageId, $reserveStock);
        } finally {
            $lock->release();
        }
    }

    /** Minutes a phone must wait between orders (Site Setting → Order safety; 0 = off). */
    public static function cooldownMinutes(): int
    {
        $value = SiteSettingsHelper::get('order_cooldown_minutes');

        return is_numeric($value) ? max(0, (int) $value) : 10;
    }

    private function persistOrder(array $cartLines, array $customer, ?string $couponCode, ?int $landingPageId, bool $reserveStock): Order
    {
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
            // Delivery charge by district (Dhaka / outside) with optional free
            // delivery over a minimum subtotal — see App\Support\Delivery.
            // Always added to the stored total, never just displayed.
            $shippingFee = Delivery::fee($customer['district'] ?? null, (float) $subtotal);

            $discount = 0;
            if ($couponCode) {
                $coupon = Coupon::findByCode($couponCode, lock: true);

                if (! $coupon) {
                    throw new RuntimeException('কুপন কোডটি সঠিক নয়।');
                }

                if ($problem = $coupon->problemFor((float) $subtotal)) {
                    throw new RuntimeException($problem);
                }

                $discount = $coupon->discountFor((float) $subtotal);

                $coupon->increment('uses');
            }

            $total = $subtotal + $shippingFee - $discount;

            $phone = $this->normalizePhone($customer['phone']);
            $ip = $customer['ip_address'] ?? null;
            $flagReasons = $this->flagReasons($phone, array_map(fn ($li) => $li['product']->id, $lineItems), $ip);

            $order = Order::create([
                'landing_page_id' => $landingPageId,
                'is_flagged' => $flagReasons !== [],
                'flag_reason' => $flagReasons ? implode('
', $flagReasons) : null,
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
                    'variant_id' => $li['variant_id'],
                    'product_name' => $li['product_name'],
                    'unit_price' => $li['unit_price'],
                    'quantity' => $li['quantity'],
                    'discount' => 0,
                    'line_total' => $li['line_total'],
                ]);

                if ($reserveStock) {
                    Stock::take($li['product']->id, $li['variant_id'], $li['quantity']);
                }
            }

            if ($reserveStock) {
                $order->forceFill(['stock_reserved' => true])->saveQuietly();
            }

            return $order;
        });
    }

    /**
     * Should this order be holding stock right now? Not when cancelled; a
     * COD order otherwise always does; a bKash order only once paid and not
     * put on hold (paid but out of stock — an admin resolves that by hand).
     */
    public static function shouldHoldStock(Order $order): bool
    {
        if ($order->status === 'cancelled') {
            return false;
        }

        return $order->payment_method !== 'bkash'
            || ($order->payment_status === 'paid' && $order->status !== 'on_hold');
    }

    /**
     * Called whenever an order's status / payment status changes (from any
     * path — see OrderObserver): gives the stock back when an order is
     * cancelled, and takes it again if a cancelled order is brought back.
     * stock_reserved makes it happen exactly once in each direction.
     */
    public function reconcileStock(Order $order): void
    {
        $hold = self::shouldHoldStock($order);

        if ($hold === (bool) $order->stock_reserved) {
            return;
        }

        DB::transaction(function () use ($order, $hold) {
            $fresh = Order::with('items')->lockForUpdate()->find($order->id);

            if (! $fresh || (bool) $fresh->stock_reserved === $hold) {
                return;
            }

            foreach ($fresh->items as $item) {
                if ($item->product_id === null) {
                    continue;
                }

                $hold
                    ? Stock::take($item->product_id, $item->variant_id, $item->quantity)
                    : Stock::release($item->product_id, $item->variant_id, $item->quantity);
            }

            $fresh->forceFill(['stock_reserved' => $hold])->saveQuietly();
            $order->setAttribute('stock_reserved', $hold);
            $order->syncOriginalAttribute('stock_reserved');
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

                // The size/variant's own stock when it is known; older
                // orders (no variant_id) fall back to the product's stock.
                $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                $variant = $item->variant_id
                    ? ProductVariant::where('id', $item->variant_id)->lockForUpdate()->first()
                    : null;
                $available = $variant ? $variant->stock : $product?->stock;

                if (! $product || $available < $item->quantity) {
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
                    Stock::take($item->product_id, $item->variant_id, $item->quantity);
                }
            }

            $order->forceFill(['stock_reserved' => true]);
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
            return 'অনুগ্রহ করে ফোন নম্বর দিন।';
        }

        $phoneVariants = app(OrderRiskService::class)->phoneVariants($normalizedPhone);

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
                return 'এই অর্ডার নম্বরের কোনো অর্ডার পাওয়া যায়নি।';
            }

            // 01712…, 8801712… and 1712… are the same number.
            $orderPhone = $this->normalizePhone($order->phone);
            $matches = in_array($orderPhone, $phoneVariants, true)
                || (strlen($normalizedPhone) >= 4 && str_ends_with($orderPhone, $normalizedPhone));

            if (! $matches) {
                return 'অর্ডার নম্বর ও ফোন নম্বর মিলছে না।';
            }

            return $order;
        }

        if (strlen($normalizedPhone) < 7) {
            return 'শুধু ফোন দিয়ে খুঁজতে হলে পুরো নম্বর দিন (অন্তত ৭ ডিজিট)।';
        }

        // Exact phone match only — a LIKE/suffix match here could return a
        // different customer's order if two numbers happen to share a tail.
        $order = Order::with('items')
            ->whereIn('phone', $phoneVariants)
            ->orderByDesc('created_at')
            ->first();

        if (! $order) {
            return 'এই ফোন নম্বর দিয়ে কোনো অর্ডার পাওয়া যায়নি।';
        }

        return $order;
    }
}
