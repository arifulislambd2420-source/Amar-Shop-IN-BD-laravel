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
    use \App\Filament\Concerns\HasAdminAreaWidget;

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

        $card = fn (Stat $stat, string $icon, string $tone): Stat => $stat
            ->icon($icon)
            ->extraAttributes(['class' => "dash-stat dash-stat--{$tone}"]);

        return [
            $card(Stat::make('Total Orders', number_format($totalOrders))->description('All-time orders'), 'heroicon-o-shopping-bag', 'primary'),
            $card(Stat::make("Today's Orders", number_format($todayOrders))->description('Placed today'), 'heroicon-o-clock', 'info'),
            $card(Stat::make('Total Sales', '৳ ' . number_format($totalSales, 2))->description('৳ ' . number_format($todaySales, 2) . ' today'), 'heroicon-o-banknotes', 'success'),
            $card(Stat::make('Products', number_format($totalProducts))->description('Catalog size'), 'heroicon-o-cube', 'primary'),
            $card(Stat::make('Customers', number_format($customers))->description('Registered customers'), 'heroicon-o-users', 'info'),
            $card(Stat::make('Total Stock', number_format($totalStock) . ' Pcs')->description(number_format($availableStock) . ' Pcs available'), 'heroicon-o-archive-box', 'warning'),
            $card(Stat::make('Total Stock Value', '৳ ' . number_format($stockValue, 2))->description('Sale/base price × stock'), 'heroicon-o-currency-bangladeshi', 'success'),
        ];
    }
}
