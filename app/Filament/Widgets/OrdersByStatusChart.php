<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Widgets\ChartWidget;

/** Dashboard: orders grouped by status (doughnut). */
class OrdersByStatusChart extends ChartWidget
{
    use \App\Filament\Concerns\HasAdminAreaWidget;

    protected static string $adminArea = \App\Support\AdminAccess::AREA_ORDERS;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'স্ট্যাটাস অনুযায়ী অর্ডার';

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
        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $statuses = $counts->keys()->all();

        return [
            'datasets' => [[
                'data' => $counts->values()->map(fn ($c) => (int) $c)->all(),
                'backgroundColor' => array_map(fn ($s) => self::COLORS[$s] ?? '#9ca3af', $statuses),
                'borderWidth' => 0,
            ]],
            'labels' => array_map(fn ($s) => OrderResource::STATUS_OPTIONS[$s] ?? (string) $s, $statuses),
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
