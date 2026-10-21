<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasAdminAreaWidget;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Order;
use App\Models\Product;
use App\Support\DashboardReport;
use App\Support\Money;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard money KPIs for the chosen dates. Orders placed and REAL sales are kept apart: real sales are
 * confirmed orders (and everything after confirming); cancelled orders never count in them, pending ones
 * not yet. Delivered sales are shown on their own, and profit is an estimate (see DashboardReport).
 */
class StatsOverview extends StatsOverviewWidget
{
    use HasAdminAreaWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '60s';

    /** 2 per row on phones, 3 on tablets and desktop. */
    protected int|array|null $columns = ['default' => 2, 'md' => 3, 'xl' => 3];

    protected function getHeading(): ?string
    {
        return __('বিক্রির সারসংক্ষেপ').' — '.DashboardReport::make($this->pageFilters)->range->label();
    }

    protected function getStats(): array
    {
        $report = DashboardReport::make($this->pageFilters);
        $placed = $report->group(null);
        $real = $report->group(DashboardReport::CONFIRMED);
        $delivered = $report->group(DashboardReport::DELIVERED);
        $cancelled = $report->group(DashboardReport::CANCELLED);

        // What still needs confirming, whatever the dates: an action list, not a report.
        $waiting = Order::query()->whereIn('status', DashboardReport::AWAITING)->count();
        $flagged = Order::query()->where('is_flagged', true)->where('status', '!=', 'cancelled')->count();

        $threshold = Product::lowStockThreshold();
        $lowStock = Product::query()->where('stock', '<=', $threshold)->count();
        $outOfStock = Product::query()->where('stock', '<=', 0)->count();

        $noCost = $report->itemsWithoutCost();
        $card = fn (Stat $stat, string $icon, string $tone): Stat => $stat
            ->icon($icon)
            ->extraAttributes(['class' => "dash-stat dash-stat--{$tone}"]);

        return [
            $card(Stat::make(__('মোট অর্ডার (বাতিলসহ)'), number_format($placed['orders']))
                ->description(__('মূল্য :amount', ['amount' => Money::taka($placed['total'])])), 'heroicon-o-shopping-bag', 'info'),

            $card(Stat::make(__('প্রকৃত বিক্রি (কনফার্মড)'), Money::taka($real['total']))
                ->description(__(':countটি অর্ডার — বাতিল বাদে, কনফার্ম হওয়ার পর', ['count' => number_format($real['orders'])])), 'heroicon-o-banknotes', 'success'),

            $card(Stat::make(__('ডেলিভারড বিক্রি'), Money::taka($delivered['total']))
                ->description(__(':countটি অর্ডার পৌঁছে গেছে', ['count' => number_format($delivered['orders'])])), 'heroicon-o-check-badge', 'success'),

            $card(Stat::make(__('বাতিল অর্ডার'), number_format($cancelled['orders']))
                ->description(__(':amount — বিক্রির হিসাবে নেই', ['amount' => Money::taka($cancelled['total'])]))
                ->url(OrderResource::getUrl('index', ['filters' => ['status' => ['value' => 'cancelled']]])), 'heroicon-o-x-circle', 'danger'),

            $card(Stat::make(__('কনফার্ম করা বাকি'), number_format($waiting))
                ->description($waiting ? __('পেন্ডিং / যাচাই চলছে').($flagged ? ' · '.__(':countটি সন্দেহজনক', ['count' => $flagged]) : '') : __('সব অর্ডার কনফার্ম হয়ে গেছে'))
                ->url(OrderResource::getUrl('index', ['filters' => ['status' => ['value' => 'pending']]])), 'heroicon-o-clock', 'warning'),

            $card(Stat::make(__('আনুমানিক লাভ (কনফার্মড)'), Money::taka($real['profit']))
                ->description(__('খরচ: পণ্য :cost + ডেলিভারি :delivery', ['cost' => Money::taka($real['cost']), 'delivery' => Money::taka($real['delivery_cost'])]).($noCost ? ' · '.__(':countটি লাইনে কস্ট প্রাইস নেই', ['count' => $noCost]) : ''))
                ->color($real['profit'] >= 0 ? 'success' : 'danger'), 'heroicon-o-chart-bar', $real['profit'] >= 0 ? 'success' : 'danger'),

            $card(Stat::make(__('কম স্টকের প্রোডাক্ট'), number_format($lowStock))
                ->description($outOfStock ? __(':countটি একদম শেষ · স্টক :limit বা কম', ['count' => $outOfStock, 'limit' => $threshold]) : __('স্টক :limit বা কম', ['limit' => $threshold]))
                ->url(ProductResource::getUrl('index')), 'heroicon-o-archive-box', $lowStock ? 'danger' : 'info'),
        ];
    }
}
