<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasAdminAreaWidget;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use App\Support\ShopTime;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard KPIs, today first: today's orders and sales (vs yesterday, with a
 * 7-day trend), orders waiting to be confirmed, low-stock products, this
 * month's sales and the customer count. Each card opens the matching list.
 *
 * Sales count real purchases only (Order::counted(): not cancelled, and not
 * an unpaid/failed bKash attempt).
 */
class StatsOverview extends StatsOverviewWidget
{
    use HasAdminAreaWidget;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    /** 2 per row on phones, 3 on tablets, all in one or two rows on desktop. */
    protected int|array|null $columns = ['default' => 2, 'md' => 3, 'xl' => 3];

    protected function getStats(): array
    {
        // Days are the shop's days (Bangladesh time), compared with the UTC times in the database.
        $ordersOn = fn (int $daysAgo) => Order::query()->counted()
            ->where('created_at', '>=', ShopTime::dayStart($daysAgo))
            ->where('created_at', '<', ShopTime::dayStart($daysAgo - 1));
        $todayOrders = $ordersOn(0)->count();
        $yesterdayOrders = $ordersOn(1)->count();
        $todaySales = (float) $ordersOn(0)->sum('total');
        $yesterdaySales = (float) $ordersOn(1)->sum('total');

        $pending = Order::query()->whereIn('status', ['pending', 'on_hold'])->count();
        $flagged = Order::query()->where('is_flagged', true)->where('status', '!=', 'cancelled')->count();

        $threshold = Product::lowStockThreshold();
        $lowStock = Product::query()->where('stock', '<=', $threshold)->count();
        $outOfStock = Product::query()->where('stock', '<=', 0)->count();

        $monthSales = (float) Order::query()->counted()->where('created_at', '>=', ShopTime::monthStart())->sum('total');
        $monthOrders = Order::query()->counted()->where('created_at', '>=', ShopTime::monthStart())->count();

        // Last 7 days of sales, for the little trend line on the sales card.
        $week = collect(range(6, 0))->map(fn ($i) => (float) $ordersOn($i)->sum('total'))->all();

        $card = fn (Stat $stat, string $icon, string $tone): Stat => $stat
            ->icon($icon)
            ->extraAttributes(['class' => "dash-stat dash-stat--{$tone}"]);

        $versus = function (float $now, float $before): string {
            if ($before <= 0) {
                return $now > 0 ? __('গতকাল কিছু ছিল না') : __('গতকালও শূন্য');
            }
            $pct = (int) round(($now - $before) / $before * 100);

            return ($pct >= 0 ? '▲ ' : '▼ ').abs($pct).'% '.__('গতকালের তুলনায়');
        };

        return [
            $card(Stat::make(__('আজকের অর্ডার'), number_format($todayOrders))
                ->description(__('গতকাল :countটি', ['count' => number_format($yesterdayOrders)]))
                ->url(OrderResource::getUrl('index')), 'heroicon-o-shopping-bag', 'primary'),

            $card(Stat::make(__('আজকের বিক্রি'), Money::taka($todaySales))
                ->description($versus($todaySales, $yesterdaySales))
                ->chart($week)
                ->color($todaySales >= $yesterdaySales ? 'success' : 'danger'), 'heroicon-o-banknotes', 'success'),

            $card(Stat::make(__('পেন্ডিং অর্ডার'), number_format($pending))
                ->description($pending ? __('কনফার্ম করা বাকি').($flagged ? ' · '.__(':countটি সন্দেহজনক', ['count' => $flagged]) : '') : __('সব অর্ডার কনফার্ম হয়ে গেছে'))
                ->url(OrderResource::getUrl('index', ['filters' => ['status' => ['value' => 'pending']]])), 'heroicon-o-clock', 'warning'),

            $card(Stat::make(__('কম স্টকের প্রোডাক্ট'), number_format($lowStock))
                ->description($outOfStock ? __(':countটি একদম শেষ · স্টক :limit বা কম', ['count' => $outOfStock, 'limit' => $threshold]) : __('স্টক :limit বা কম', ['limit' => $threshold]))
                ->url(ProductResource::getUrl('index')), 'heroicon-o-archive-box', $lowStock ? 'danger' : 'info'),

            $card(Stat::make(__('এই মাসের বিক্রি'), Money::taka($monthSales))
                ->description(__(':countটি অর্ডার', ['count' => number_format($monthOrders)])), 'heroicon-o-calendar-days', 'success'),

            $card(Stat::make(__('গ্রাহক'), number_format(User::count()))
                ->description(__('রেজিস্টার করা অ্যাকাউন্ট')), 'heroicon-o-users', 'info'),
        ];
    }
}
