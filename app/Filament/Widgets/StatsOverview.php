<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasAdminAreaWidget;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
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
        $today = today();
        $yesterday = today()->subDay();

        $ordersOn = fn ($day) => Order::query()->counted()->whereDate('created_at', $day);
        $todayOrders = $ordersOn($today)->count();
        $yesterdayOrders = $ordersOn($yesterday)->count();
        $todaySales = (float) $ordersOn($today)->sum('total');
        $yesterdaySales = (float) $ordersOn($yesterday)->sum('total');

        $pending = Order::query()->whereIn('status', ['pending', 'on_hold'])->count();
        $flagged = Order::query()->where('is_flagged', true)->where('status', '!=', 'cancelled')->count();

        $threshold = Product::lowStockThreshold();
        $lowStock = Product::query()->where('stock', '<=', $threshold)->count();
        $outOfStock = Product::query()->where('stock', '<=', 0)->count();

        $monthSales = (float) Order::query()->counted()->where('created_at', '>=', $today->copy()->startOfMonth())->sum('total');
        $monthOrders = Order::query()->counted()->where('created_at', '>=', $today->copy()->startOfMonth())->count();

        // Last 7 days of sales, for the little trend line on the sales card.
        $week = collect(range(6, 0))->map(fn ($i) => (float) $ordersOn(today()->subDays($i))->sum('total'))->all();

        $card = fn (Stat $stat, string $icon, string $tone): Stat => $stat
            ->icon($icon)
            ->extraAttributes(['class' => "dash-stat dash-stat--{$tone}"]);

        $versus = function (float $now, float $before): string {
            if ($before <= 0) {
                return $now > 0 ? 'গতকাল কিছু ছিল না' : 'গতকালও শূন্য';
            }
            $pct = (int) round(($now - $before) / $before * 100);

            return ($pct >= 0 ? '▲ ' : '▼ ').abs($pct).'% গতকালের তুলনায়';
        };

        return [
            $card(Stat::make('আজকের অর্ডার', number_format($todayOrders))
                ->description('গতকাল '.number_format($yesterdayOrders).'টি')
                ->url(OrderResource::getUrl('index')), 'heroicon-o-shopping-bag', 'primary'),

            $card(Stat::make('আজকের বিক্রি', Money::taka($todaySales))
                ->description($versus($todaySales, $yesterdaySales))
                ->chart($week)
                ->color($todaySales >= $yesterdaySales ? 'success' : 'danger'), 'heroicon-o-banknotes', 'success'),

            $card(Stat::make('পেন্ডিং অর্ডার', number_format($pending))
                ->description($pending ? 'কনফার্ম করা বাকি'.($flagged ? " · {$flagged}টি সন্দেহজনক" : '') : 'সব অর্ডার কনফার্ম হয়ে গেছে')
                ->url(OrderResource::getUrl('index', ['filters' => ['status' => ['value' => 'pending']]])), 'heroicon-o-clock', 'warning'),

            $card(Stat::make('কম স্টকের প্রোডাক্ট', number_format($lowStock))
                ->description($outOfStock ? "{$outOfStock}টি একদম শেষ · স্টক {$threshold} বা কম" : "স্টক {$threshold} বা কম")
                ->url(ProductResource::getUrl('index')), 'heroicon-o-archive-box', $lowStock ? 'danger' : 'info'),

            $card(Stat::make('এই মাসের বিক্রি', Money::taka($monthSales))
                ->description(number_format($monthOrders).'টি অর্ডার'), 'heroicon-o-calendar-days', 'success'),

            $card(Stat::make('গ্রাহক', number_format(User::count()))
                ->description('রেজিস্টার করা অ্যাকাউন্ট'), 'heroicon-o-users', 'info'),
        ];
    }
}
