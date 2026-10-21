<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasAdminAreaWidget;
use App\Support\DashboardReport;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard: customers — all, new and repeat — for the chosen dates. A customer is a phone number on an
 * order that was not cancelled (most people buy without an account).
 */
class CustomerStats extends StatsOverviewWidget
{
    use HasAdminAreaWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 8;

    protected ?string $pollingInterval = null;

    protected int|array|null $columns = ['default' => 1, 'md' => 3];

    protected function getHeading(): ?string
    {
        return __('কাস্টমার').' — '.DashboardReport::make($this->pageFilters)->range->label();
    }

    protected function getStats(): array
    {
        $c = DashboardReport::make($this->pageFilters)->customers();
        $card = fn (Stat $stat, string $icon, string $tone): Stat => $stat->icon($icon)->extraAttributes(['class' => "dash-stat dash-stat--{$tone}"]);

        return [
            $card(Stat::make(__('মোট কাস্টমার'), number_format($c['total']))
                ->description(__('এ পর্যন্ত অর্ডার করেছেন (বাতিল বাদে)')), 'heroicon-o-users', 'info'),
            $card(Stat::make(__('নতুন কাস্টমার'), number_format($c['new']))
                ->description(__('এই সময়ে প্রথমবার অর্ডার করেছেন')), 'heroicon-o-user-plus', 'success'),
            $card(Stat::make(__('রিপিট কাস্টমার'), number_format($c['repeat']))
                ->description(__('এই সময়ের :count জনের মধ্যে — আগেও কিনেছেন বা একাধিকবার', ['count' => number_format($c['in_range'])])), 'heroicon-o-arrow-path', 'warning'),
        ];
    }
}
