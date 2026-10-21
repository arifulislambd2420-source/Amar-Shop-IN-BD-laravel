<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasAdminAreaWidget;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\DashboardReport;
use App\Support\Money;
use App\Support\ReportRange;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The four main cards at the top of the dashboard — orders, confirmed (real) sales, orders waiting to be
 * confirmed, and estimated profit — each compared with the same number of days just before. Everything else
 * (profit table, products, customers, payments, stock) is under "Details" further down.
 *
 * Real sales are confirmed orders and everything after confirming; cancelled orders never count in them.
 */
class StatsOverview extends StatsOverviewWidget
{
    use HasAdminAreaWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    /** 2 per row on phones, 4 in one row from tablets up. */
    protected int|array|null $columns = ['default' => 2, 'md' => 4];

    protected function getHeading(): ?string
    {
        return null;
    }

    /** "▲ 12% · before ৳1,000" as the description, with the arrow's colour (green up, red down, grey flat). */
    private function trend(Stat $stat, float $now, float $before, string $beforeText, string $suffix = ''): Stat
    {
        $change = DashboardReport::change($now, $before);

        $text = match (true) {
            $change['pct'] === null && $change['dir'] === 'up' => __('নতুন'),
            $change['pct'] === null => __('আগের সময়ের মতো'),
            default => ($change['dir'] === 'down' ? '▼ ' : ($change['dir'] === 'up' ? '▲ ' : '')).$change['pct'].'%',
        };

        return $stat
            ->description($text.' · '.__('আগে :value', ['value' => $beforeText]).$suffix)
            ->descriptionColor(match ($change['dir']) {
                'up' => 'success',
                'down' => 'danger',
                default => 'gray',
            });
    }

    protected function getStats(): array
    {
        $report = DashboardReport::make($this->pageFilters);
        $before = $report->previous();
        $today = $report->range->key === ReportRange::TODAY;

        $placed = $report->group(null);
        $placedBefore = $before->group(null);
        $real = $report->group(DashboardReport::CONFIRMED);
        $realBefore = $before->group(DashboardReport::CONFIRMED);

        // Waiting to be confirmed — what still needs doing, whatever the dates.
        $waiting = Order::query()->whereIn('status', DashboardReport::AWAITING)->count();
        $awaitingNow = $report->group(DashboardReport::AWAITING)['orders'];
        $awaitingBefore = $before->group(DashboardReport::AWAITING)['orders'];
        $noCost = $report->itemsWithoutCost();

        $card = fn (Stat $stat, string $icon): Stat => $stat->icon($icon)->extraAttributes(['class' => 'dash-stat']);

        return [
            $this->trend(
                $card(Stat::make($today ? __('আজকের অর্ডার') : __('অর্ডার'), number_format($placed['orders'])), 'heroicon-o-shopping-bag')
                    ->url(OrderResource::getUrl('index')),
                $placed['orders'], $placedBefore['orders'], number_format($placedBefore['orders']),
            ),

            $this->trend(
                $card(Stat::make(__('কনফার্মড বিক্রি'), Money::taka($real['total'])), 'heroicon-o-banknotes'),
                $real['total'], $realBefore['total'], Money::taka($realBefore['total']),
            ),

            $card(Stat::make(__('কনফার্মের অপেক্ষায়'), number_format($waiting))
                ->description($waiting
                    ? __('এই সময়ে এসেছে :now · আগে :before', ['now' => number_format($awaitingNow), 'before' => number_format($awaitingBefore)])
                    : __('সব অর্ডার কনফার্ম হয়ে গেছে'))
                ->descriptionColor($waiting ? 'warning' : 'success')
                ->url(OrderResource::getUrl('index', ['filters' => ['status' => ['value' => 'pending']]])), 'heroicon-o-clock'),

            $this->trend(
                $card(Stat::make(__('আনুমানিক লাভ'), Money::taka($real['profit'])), 'heroicon-o-chart-bar'),
                $real['profit'], $realBefore['profit'], Money::taka($realBefore['profit']),
                // a note when some products have no cost price (the profit would look too high)
                $noCost ? ' · ⚠ '.__(':countটি লাইনে কস্ট প্রাইস নেই', ['count' => $noCost]) : '',
            ),
        ];
    }
}
