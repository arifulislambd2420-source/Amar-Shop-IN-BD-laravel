<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasAdminAreaWidget;
use App\Models\Order;
use App\Support\ShopTime;
use App\Support\SiteSettingsHelper;
use Filament\Widgets\ChartWidget;

/**
 * Dashboard: sales (৳) per day for the last 7 / 30 / 90 days (filter on the
 * chart). Counts the same orders as the sales cards: Order::counted() — not
 * cancelled, and not an unpaid/failed bKash attempt.
 */
class SalesChart extends ChartWidget
{
    use HasAdminAreaWidget;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {

        return __('বিক্রির চার্ট');

    }

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return ['7' => __('গত ৭ দিন'), '30' => __('গত ৩০ দিন'), '90' => __('গত ৯০ দিন')];
    }

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = null;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = in_array($this->filter, ['7', '30', '90'], true) ? (int) $this->filter : 30;
        // One bar per shop day (Bangladesh time): sum in PHP, because the database dates are UTC.
        $from = ShopTime::dayStart($days - 1);
        $totals = [];

        Order::query()->counted()->where('created_at', '>=', $from)->get(['created_at', 'total'])
            ->each(function (Order $order) use (&$totals): void {
                $day = ShopTime::local($order->created_at)->toDateString();
                $totals[$day] = ($totals[$day] ?? 0) + (float) $order->total;
            });

        $from = ShopTime::local($from);

        $labels = [];
        $data = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $labels[] = $day->locale('bn')->translatedFormat('j M');
            $data[] = round((float) ($totals[$day->toDateString()] ?? 0), 2);
        }

        $brand = SiteSettingsHelper::color('brand');

        return [
            'datasets' => [[
                'label' => __('বিক্রি (৳)'),
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
