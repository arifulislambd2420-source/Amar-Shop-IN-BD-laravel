<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasAdminAreaWidget;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Support\DashboardReport;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/** Dashboard: the best-selling and the slowest-selling products of the chosen dates (confirmed sales only). */
class TopProducts extends Widget
{
    use HasAdminAreaWidget;
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.product-sales';

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $report = DashboardReport::make($this->pageFilters);
        $link = fn (array $row): string => ProductResource::getUrl('edit', ['record' => $row['id']]);

        return [
            'label' => $report->range->label(),
            'best' => array_map(fn (array $r) => $r + ['url' => $link($r)], array_values(array_filter($report->products(5, true), fn ($r) => $r['qty'] > 0))),
            'slow' => array_map(fn (array $r) => $r + ['url' => $link($r)], $report->products(5, false)),
            'threshold' => Product::lowStockThreshold(),
        ];
    }
}
