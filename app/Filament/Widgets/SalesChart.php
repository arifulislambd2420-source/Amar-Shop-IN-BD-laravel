<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasAdminAreaWidget;
use App\Support\DashboardReport;
use App\Support\SiteSettingsHelper;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

/**
 * Dashboard: a line per shop day for the chosen dates — everything ordered, and the real (confirmed) sales.
 * The gap between the two is what is still waiting or was cancelled.
 */
class SalesChart extends ChartWidget
{
    use HasAdminAreaWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('বিক্রির চার্ট');
    }

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = null;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $series = DashboardReport::make($this->pageFilters)->series();
        $brand = SiteSettingsHelper::color('brand');

        return [
            'datasets' => [
                [
                    'label' => __('প্রকৃত বিক্রি (কনফার্মড) ৳'),
                    'data' => array_map(fn ($d) => round($d['real'], 2), array_values($series)),
                    'borderColor' => $brand,
                    'backgroundColor' => $brand.'22',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 2,
                ],
                [
                    'label' => __('মোট অর্ডার (বাতিলসহ) ৳'),
                    'data' => array_map(fn ($d) => round($d['placed'], 2), array_values($series)),
                    'borderColor' => '#94a3b8',
                    'backgroundColor' => 'transparent',
                    'borderDash' => [5, 4],
                    'tension' => 0.35,
                    'pointRadius' => 0,
                ],
            ],
            'labels' => array_map(fn (string $day) => Carbon::parse($day)->locale('bn')->translatedFormat('j M'), array_keys($series)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}
