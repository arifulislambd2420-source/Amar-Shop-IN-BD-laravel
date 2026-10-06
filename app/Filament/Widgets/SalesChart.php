<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Support\SiteSettingsHelper;
use Filament\Widgets\ChartWidget;

/**
 * Dashboard: sales (৳) per day for the last 30 days. Counts the same orders
 * as the "Total Sales" card (everything not cancelled).
 */
class SalesChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'গত ৩০ দিনের বিক্রি';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = null;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $from = now()->subDays(29)->startOfDay();

        $totals = Order::query()
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, SUM(total) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $data = [];

        for ($i = 0; $i < 30; $i++) {
            $day = $from->copy()->addDays($i);
            $labels[] = $day->format('d M');
            $data[] = round((float) ($totals[$day->toDateString()] ?? 0), 2);
        }

        $brand = SiteSettingsHelper::color('brand');

        return [
            'datasets' => [[
                'label' => 'বিক্রি (৳)',
                'data' => $data,
                'borderColor' => $brand,
                'backgroundColor' => $brand.'22',
                'fill' => true,
                'tension' => 0.35,
                'pointRadius' => 2,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}
