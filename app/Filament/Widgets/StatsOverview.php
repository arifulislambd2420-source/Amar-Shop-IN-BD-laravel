<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard KPIs — replicates the old admin dashboard cards
 * (total/today orders, products, customers, stock, sales, stock value).
 */
class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $totalOrders = Order::count();
        $todayOrders = Order::whereDate('created_at', today())->count();

        $totalSales = (float) Order::where('status', '!=', 'cancelled')->sum('total');
        $todaySales = (float) Order::where('status', '!=', 'cancelled')
            ->whereDate('created_at', today())
            ->sum('total');

        $totalProducts = Product::count();
        $customers = User::count();

        $totalStock = (int) Product::sum('stock');
        $availableStock = (int) Product::where('stock', '>', 0)->sum('stock');

        $stockValue = (float) Product::query()
            ->selectRaw('COALESCE(SUM(COALESCE(sale_price, price) * stock), 0) AS v')
            ->value('v');

        return [
            Stat::make('Total Orders', number_format($totalOrders))
                ->description('All-time orders')
                ->color('primary'),
            Stat::make("Today's Orders", number_format($todayOrders))
                ->description('Placed today')
                ->color('info'),
            Stat::make('Total Sales', '৳ ' . number_format($totalSales, 2))
                ->description('৳ ' . number_format($todaySales, 2) . ' today')
                ->color('success'),
            Stat::make('Products', number_format($totalProducts))
                ->description('Catalog size')
                ->color('primary'),
            Stat::make('Customers', number_format($customers))
                ->description('Registered customers')
                ->color('info'),
            Stat::make('Total Stock', number_format($totalStock) . ' Pcs')
                ->description(number_format($availableStock) . ' Pcs available')
                ->color('warning'),
            Stat::make('Total Stock Value', '৳ ' . number_format($stockValue, 2))
                ->description('Sale/base price × stock')
                ->color('success'),
        ];
    }
}
