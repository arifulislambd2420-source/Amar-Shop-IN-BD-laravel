<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasAdminAreaWidget;
use App\Filament\Resources\Orders\OrderResource;
use App\Support\AdminAccess;
use App\Support\DashboardReport;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Dashboard: a small list of how many orders are in each status for the chosen dates, with a thin bar for the
 * share of all orders. Each row opens the order list on that status. With no orders it says so, instead of
 * showing an empty box.
 */
class OrderStatusSummary extends Widget
{
    use HasAdminAreaWidget;
    use InteractsWithPageFilters;

    protected static string $adminArea = AdminAccess::AREA_ORDERS;

    protected string $view = 'filament.widgets.order-status-summary';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected function getViewData(): array
    {
        $report = DashboardReport::make($this->pageFilters);
        $labels = OrderResource::statuses();
        $counts = $report->statusCounts();
        $total = array_sum($counts);

        $rows = [];

        foreach ($counts as $status => $count) {
            $rows[] = [
                'label' => $labels[$status] ?? $status,
                'count' => $count,
                'pct' => $total ? round($count / $total * 100) : 0,
                
                'url' => OrderResource::getUrl('index', ['filters' => ['status' => ['value' => $status]]]),
            ];
        }

        return ['label' => $report->range->label(), 'total' => $total, 'rows' => $rows];
    }
}
