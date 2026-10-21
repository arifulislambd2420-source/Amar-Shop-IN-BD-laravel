<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * The numbers behind the admin dashboard, for one date range (see ReportRange). Orders are grouped by
 * what they mean for the business:
 *
 *   placed      every order made in the range (cancelled ones included)
 *   awaiting    pending / on hold — not confirmed yet
 *   confirmed   confirmed or any later step (shipped … completed): the REAL sales
 *   delivered   delivered / completed: money that has really come in
 *   cancelled   cancelled — never part of the real sales
 *
 * Profit is an estimate: order total (what the customer pays, delivery charge included) minus the product
 * cost (price kept on each order line when it was placed, else the product's current cost price) minus the
 * shop's own delivery cost per order (Settings → Delivery & Orders).
 */
class DashboardReport
{
    public const AWAITING = ['pending', 'on_hold'];

    public const CONFIRMED = ['processing', 'shipped', 'out_for_delivery', 'delivered', 'completed'];

    public const DELIVERED = ['delivered', 'completed'];

    public const CANCELLED = ['cancelled'];

    /** @var array<string, array{orders: int, total: float, shipping: float, qty: int, cost: float}>|null */
    private ?array $byStatus = null;

    public function __construct(public readonly ReportRange $range)
    {
    }

    /** The report for the equal stretch of days right before this one. */
    public function previous(): self
    {
        return new self($this->range->previous());
    }

    /**
     * How $now compares with $before: ['pct' => int|null, 'dir' => 'up'|'down'|'flat']. pct is null when there
     * was nothing before to compare with.
     *
     * @return array{pct: int|null, dir: string}
     */
    public static function change(float $now, float $before): array
    {
        if ($before == 0.0) {
            return ['pct' => null, 'dir' => $now > 0 ? 'up' : 'flat'];
        }

        $pct = (int) round(($now - $before) / abs($before) * 100);

        return ['pct' => abs($pct), 'dir' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat')];
    }

    public static function make(?array $filters): self
    {
        return new self(ReportRange::fromFilters($filters));
    }

    /** The shop's own cost to send one order (courier fee it pays), ৳. */
    public static function deliveryCostPerOrder(): float
    {
        $value = SiteSettingsHelper::get('delivery_cost_per_order');

        return is_numeric($value) ? max(0.0, (float) $value) : 0.0;
    }

    private function orders()
    {
        return Order::query()
            ->where('created_at', '>=', $this->range->start())
            ->where('created_at', '<', $this->range->end());
    }

    /** @return array<string, array{orders: int, total: float, shipping: float, qty: int, cost: float}> */
    private function byStatus(): array
    {
        if ($this->byStatus !== null) {
            return $this->byStatus;
        }

        $rows = [];

        $this->orders()
            ->selectRaw('status, COUNT(*) as c, SUM(total) as t, SUM(shipping_fee) as s')
            ->groupBy('status')
            ->get()
            ->each(function ($r) use (&$rows) {
                $rows[$r->status] = ['orders' => (int) $r->c, 'total' => (float) $r->t, 'shipping' => (float) $r->s, 'qty' => 0, 'cost' => 0.0];
            });

        DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.created_at', '>=', $this->range->start())
            ->where('orders.created_at', '<', $this->range->end())
            ->selectRaw('orders.status as status, SUM(order_items.quantity) as q, SUM(order_items.quantity * COALESCE(order_items.unit_cost, products.cost_price, 0)) as cost')
            ->groupBy('orders.status')
            ->get()
            ->each(function ($r) use (&$rows) {
                $rows[$r->status] ??= ['orders' => 0, 'total' => 0.0, 'shipping' => 0.0, 'qty' => 0, 'cost' => 0.0];
                $rows[$r->status]['qty'] = (int) $r->q;
                $rows[$r->status]['cost'] = (float) $r->cost;
            });

        return $this->byStatus = $rows;
    }

    /** Orders per status (every known status, zero when none). @return array<string, int> */
    public function statusCounts(): array
    {
        $counts = array_fill_keys(array_keys(\App\Filament\Resources\Orders\OrderResource::STATUS_OPTIONS), 0);

        foreach ($this->byStatus() as $status => $row) {
            $counts[$status] = $row['orders'];
        }

        return $counts;
    }

    /**
     * Totals for a group of statuses (null = every order placed).
     *
     * @param  list<string>|null  $statuses
     * @return array{orders: int, total: float, shipping: float, qty: int, cost: float, delivery_cost: float, profit: float}
     */
    public function group(?array $statuses): array
    {
        $sum = ['orders' => 0, 'total' => 0.0, 'shipping' => 0.0, 'qty' => 0, 'cost' => 0.0];

        foreach ($this->byStatus() as $status => $row) {
            if ($statuses === null || in_array($status, $statuses, true)) {
                foreach ($sum as $key => $_) {
                    $sum[$key] += $row[$key];
                }
            }
        }

        $deliveryCost = $sum['orders'] * self::deliveryCostPerOrder();

        return $sum + ['delivery_cost' => $deliveryCost, 'profit' => $sum['total'] - $sum['cost'] - $deliveryCost];
    }

    /** The sales & profit table: label => totals. Cancelled is shown but has no profit. */
    public function table(): array
    {
        return [
            'placed' => ['label' => 'মোট অর্ডার (বাতিলসহ)'] + $this->group(null),
            'awaiting' => ['label' => 'কনফার্মের অপেক্ষায় (পেন্ডিং / যাচাই চলছে)'] + $this->group(self::AWAITING),
            'confirmed' => ['label' => 'কনফার্মড বিক্রি (প্রকৃত বিক্রি)'] + $this->group(self::CONFIRMED),
            'delivered' => ['label' => 'ডেলিভারড / সম্পন্ন বিক্রি'] + $this->group(self::DELIVERED),
            'cancelled' => ['label' => 'বাতিল (বিক্রির হিসাবে নেই)'] + $this->group(self::CANCELLED),
        ];
    }

    /** Order lines of confirmed sales with no cost price anywhere: profit is overstated for them. */
    public function itemsWithoutCost(): int
    {
        return (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.created_at', '>=', $this->range->start())
            ->where('orders.created_at', '<', $this->range->end())
            ->whereIn('orders.status', self::CONFIRMED)
            ->whereRaw('COALESCE(order_items.unit_cost, products.cost_price, 0) <= 0')
            ->count();
    }

    /**
     * Units sold (confirmed sales only) per product, best first — or slowest first, including products that
     * sold nothing at all.
     *
     * @return list<array{id: int, name: string, qty: int, revenue: float, stock: int|null}>
     */
    public function products(int $limit, bool $best = true): array
    {
        $sold = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', $this->range->start())
            ->where('orders.created_at', '<', $this->range->end())
            ->whereIn('orders.status', self::CONFIRMED)
            ->whereNotNull('order_items.product_id')
            ->selectRaw('order_items.product_id as id, SUM(order_items.quantity) as q, SUM(order_items.line_total) as rev')
            ->groupBy('order_items.product_id')
            ->get()
            ->keyBy('id');

        $names = Product::query()->whereIn('id', $sold->keys())->pluck('name', 'id');

        $list = $best
            ? $sold->map(fn ($r) => ['id' => (int) $r->id, 'name' => $names[$r->id] ?? '#'.$r->id, 'qty' => (int) $r->q, 'revenue' => (float) $r->rev])
            : Product::query()->storefront()->get(['id', 'name', 'stock'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'qty' => (int) ($sold[$p->id]->q ?? 0), 'revenue' => (float) ($sold[$p->id]->rev ?? 0)]);

        return $list
            ->sort(fn ($a, $b) => $best ? [$b['qty'], $b['revenue']] <=> [$a['qty'], $a['revenue']] : [$a['qty'], $a['revenue']] <=> [$b['qty'], $b['revenue']])
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Customers are people, told apart by phone number (orders, not accounts — most buy as guests).
     * Counted on orders that were not cancelled.
     *
     * @return array{total: int, new: int, repeat: int, in_range: int}
     */
    public function customers(): array
    {
        $live = fn () => Order::query()->where('status', '!=', 'cancelled');

        $total = (int) $live()->distinct()->count('phone');

        $inRange = $live()
            ->where('created_at', '>=', $this->range->start())
            ->where('created_at', '<', $this->range->end())
            ->selectRaw('phone, COUNT(*) as c')
            ->groupBy('phone')
            ->pluck('c', 'phone');

        if ($inRange->isEmpty()) {
            return ['total' => $total, 'new' => 0, 'repeat' => 0, 'in_range' => 0];
        }

        // When each of these people first ordered.
        $first = $live()->whereIn('phone', $inRange->keys())->selectRaw('phone, MIN(created_at) as f')->groupBy('phone')->pluck('f', 'phone');
        $start = $this->range->start();

        $new = 0;
        $repeat = 0;

        foreach ($inRange as $phone => $count) {
            $firstAt = \Illuminate\Support\Carbon::parse((string) $first[$phone], 'UTC');

            if ($firstAt->greaterThanOrEqualTo($start)) {
                $new++;
            }

            // Ordered before this range, or more than once within it.
            if ($firstAt->lessThan($start) || $count >= 2) {
                $repeat++;
            }
        }

        return ['total' => $total, 'new' => $new, 'repeat' => $repeat, 'in_range' => $inRange->count()];
    }

    /** Confirmed sales by payment method, plus delivery charges and cancelled orders. */
    public function payments(): array
    {
        $methods = Order::query()
            ->where('created_at', '>=', $this->range->start())
            ->where('created_at', '<', $this->range->end())
            ->whereIn('status', self::CONFIRMED)
            ->selectRaw('payment_method, COUNT(*) as c, SUM(total) as t')
            ->groupBy('payment_method')
            ->orderByDesc('t')
            ->get()
            ->map(fn ($r) => ['method' => (string) $r->payment_method, 'orders' => (int) $r->c, 'total' => (float) $r->t])
            ->all();

        $confirmed = $this->group(self::CONFIRMED);
        $cancelled = $this->group(self::CANCELLED);

        return [
            'methods' => $methods,
            'delivery_charge' => $confirmed['shipping'],
            'delivery_cost' => $confirmed['delivery_cost'],
            'cancelled_orders' => $cancelled['orders'],
            'cancelled_total' => $cancelled['total'],
        ];
    }

    /**
     * One entry per shop day: every order placed vs confirmed (real) sales.
     *
     * @return array<string, array{placed: float, real: float}> keyed by Y-m-d
     */
    public function series(): array
    {
        $days = array_fill_keys($this->range->dates(), ['placed' => 0.0, 'real' => 0.0]);

        $this->orders()->get(['created_at', 'total', 'status'])->each(function (Order $order) use (&$days) {
            $day = ShopTime::local($order->created_at)->toDateString();

            if (! isset($days[$day])) {
                return;
            }

            $days[$day]['placed'] += (float) $order->total;

            if (in_array($order->status, self::CONFIRMED, true)) {
                $days[$day]['real'] += (float) $order->total;
            }
        });

        return $days;
    }
}
