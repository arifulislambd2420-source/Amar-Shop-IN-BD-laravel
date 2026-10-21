<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasAdminAreaWidget;
use App\Filament\Resources\Orders\OrderResource;
use App\Support\DashboardReport;
use App\Support\AdminAccess;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Dashboard: orders grouped by status (doughnut). */
class OrdersByStatusChart extends ChartWidget
{
    use HasAdminAreaWidget;
    use InteractsWithPageFilters;

    protected static string $adminArea = AdminAccess::AREA_ORDERS;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): ?string
    {

        return __('স্ট্যাটাস অনুযায়ী অর্ডার');

    }

    protected ?string $maxHeight = '260px';

    protected ?string $pollingInterval = null;

    private const COLORS = [
        'pending' => '#f59e0b',
        'processing' => '#0ea5e9',
        'shipped' => '#6366f1',
        'out_for_delivery' => '#14b8a6',
        'delivered' => '#16a34a',
        'completed' => '#22c55e',
        'cancelled' => '#ef4444',
        'on_hold' => '#f97316',
    ];

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        // The chosen dates; statuses with no orders are left out.
        $counts = collect(DashboardReport::make($this->pageFilters)->statusCounts())->filter(fn ($c) => $c > 0);

        $statuses = $counts->keys()->all();

        return [
            'datasets' => [[
                'data' => $counts->values()->map(fn ($c) => (int) $c)->all(),
                'backgroundColor' => array_map(fn ($s) => self::COLORS[$s] ?? '#9ca3af', $statuses),
                'borderWidth' => 0,
            ]],
            'labels' => array_map(fn ($s) => OrderResource::statuses()[$s] ?? (string) $s, $statuses),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '62%',
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
