<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasAdminAreaWidget;
use App\Filament\Resources\Orders\OrderResource;
use App\Support\AdminAccess;
use App\Support\DashboardReport;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard: how many orders are in each status (Pending, Confirmed, Shipped, … Cancelled) for the chosen
 * dates. Each card opens the order list on that status.
 */
class OrderStatusSummary extends StatsOverviewWidget
{
    use HasAdminAreaWidget;
    use InteractsWithPageFilters;

    protected static string $adminArea = AdminAccess::AREA_ORDERS;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected int|array|null $columns = ['default' => 2, 'md' => 4, 'xl' => 4];

    private const TONES = [
        'pending' => 'warning',
        'processing' => 'info',
        'shipped' => 'info',
        'out_for_delivery' => 'info',
        'delivered' => 'success',
        'completed' => 'success',
        'on_hold' => 'warning',
        'cancelled' => 'danger',
    ];

    protected function getHeading(): ?string
    {
        return __('অর্ডারের অবস্থা').' — '.DashboardReport::make($this->pageFilters)->range->label();
    }

    protected function getStats(): array
    {
        $labels = OrderResource::statuses();
        $counts = DashboardReport::make($this->pageFilters)->statusCounts();

        return collect(self::TONES)->map(fn (string $tone, string $status) => Stat::make($labels[$status] ?? $status, number_format($counts[$status] ?? 0))
            ->url(OrderResource::getUrl('index', ['filters' => ['status' => ['value' => $status]]]))
            ->extraAttributes(['class' => "dash-stat dash-stat--{$tone}"]))
            ->values()
            ->all();
    }
}
