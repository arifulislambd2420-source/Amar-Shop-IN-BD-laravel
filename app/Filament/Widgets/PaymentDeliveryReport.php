<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasAdminAreaWidget;
use App\Filament\Resources\Orders\OrderResource;
use App\Support\DashboardReport;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Dashboard: confirmed sales by payment method (cash on delivery, bKash, …), the delivery charges
 * customers paid, the shop's own delivery cost, and the cancelled orders.
 */
class PaymentDeliveryReport extends Widget
{
    use HasAdminAreaWidget;
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.payment-delivery-report';

    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $report = DashboardReport::make($this->pageFilters);
        $labels = OrderResource::paymentMethods();
        $data = $report->payments();

        $data['methods'] = array_map(fn (array $m) => $m + ['label' => $labels[$m['method']] ?? ($m['method'] ?: '—')], $data['methods']);

        return ['label' => $report->range->label()] + $data;
    }
}
