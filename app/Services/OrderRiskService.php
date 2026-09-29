<?php

namespace App\Services;

use App\Models\Order;

/**
 * Heuristics that decide whether a *new* order should be flagged for an
 * admin to look at. It never blocks: OrderService still creates the order,
 * just with is_flagged=true and the reasons in flag_reason.
 *
 * Relationship to the "Fraud API" admin page: that page only stores an API
 * URL/key/active toggle (fraud_api_configs) — nothing in the app calls it
 * yet, and its request/response format isn't defined anywhere. These rules
 * use only data we already have, so they work today and don't depend on
 * it; when a Fraud API check exists it can simply add another reason to the
 * list evaluate() returns.
 *
 * Thresholds are constants so they're easy to tune. Mobile carriers in
 * Bangladesh put many customers behind one shared IP, which is exactly why
 * these flag for review instead of blocking.
 */
class OrderRiskService
{
    /** Same phone, same product, within this many minutes => duplicate. */
    public const DUPLICATE_WINDOW_MINUTES = 5;

    /** More than this many orders from one IP inside the window => flag. */
    public const IP_MAX_ORDERS = 4;

    public const IP_WINDOW_MINUTES = 60;

    /** Prior cancelled/returned orders on this phone at which we flag. */
    public const BAD_HISTORY_THRESHOLD = 2;

    /**
     * @param  string  $phone  digits-only, as stored on orders
     * @param  list<int>  $productIds  products in the order being placed
     * @return list<string> human-readable reasons; empty = looks normal
     */
    public function evaluate(string $phone, array $productIds, ?string $ip): array
    {
        $reasons = [];
        $variants = $this->phoneVariants($phone);

        // 1) Same phone re-ordering the same product moments ago.
        $duplicate = Order::counted()
            ->whereIn('phone', $variants)
            ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES))
            ->whereHas('items', fn ($q) => $q->whereIn('product_id', $productIds))
            ->with(['items' => fn ($q) => $q->whereIn('product_id', $productIds)])
            ->latest('created_at')
            ->first();

        if ($duplicate) {
            $minutes = max(1, (int) $duplicate->created_at->diffInMinutes(now()));
            $product = $duplicate->items->first()?->product_name;
            $reasons[] = "Duplicate: this phone ordered the same product ({$product}) {$minutes} min ago — {$duplicate->invoice_no}.";
        }

        // 2) Unusual order rate from one IP.
        if ($ip !== null && $ip !== '') {
            $fromIp = Order::counted()
                ->where('ip_address', $ip)
                ->where('created_at', '>=', now()->subMinutes(self::IP_WINDOW_MINUTES))
                ->count();

            if ($fromIp >= self::IP_MAX_ORDERS) {
                $reasons[] = "High rate: {$fromIp} earlier orders from this IP ({$ip}) in the last ".self::IP_WINDOW_MINUTES.' minutes.';
            }
        }

        // 3) This phone's history of cancelled / returned orders. Steadfast
        // reports a returned parcel as courier_status "cancelled".
        $bad = Order::whereIn('phone', $variants)
            ->where(fn ($q) => $q->where('status', 'cancelled')->orWhereIn('courier_status', ['cancelled', 'returned', 'return']))
            ->count();

        if ($bad >= self::BAD_HISTORY_THRESHOLD) {
            $reasons[] = "History: this phone has {$bad} previously cancelled/returned orders.";
        }

        return $reasons;
    }

    /**
     * Orders store whatever digits the customer typed, so one Bangladeshi
     * mobile can appear as 017…, 88017… or 17…; match all three (whereIn,
     * so the phone index still applies).
     *
     * @return list<string>
     */
    private function phoneVariants(string $digits): array
    {
        if (preg_match('/^(?:880|0)?(1[3-9]\d{8})$/', $digits, $m)) {
            return ['0'.$m[1], '880'.$m[1], $m[1]];
        }

        return [$digits];
    }
}
