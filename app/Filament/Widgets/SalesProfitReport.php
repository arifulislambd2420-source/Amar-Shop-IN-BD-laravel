<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasAdminAreaWidget;
use App\Support\DashboardReport;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Dashboard: the sales & profit table for the chosen dates — every order placed, those waiting for
 * confirmation, the real (confirmed) sales, the delivered sales, and the cancelled ones kept apart.
 */
class SalesProfitReport extends Widget
{
    use HasAdminAreaWidget;
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.sales-profit-report';

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $report = DashboardReport::make($this->pageFilters);

        return [
            'label' => $report->range->label(),
            'rows' => $report->table(),
            'noCost' => $report->itemsWithoutCost(),
            'perOrderDeliveryCost' => DashboardReport::deliveryCostPerOrder(),
        ];
    }
}
