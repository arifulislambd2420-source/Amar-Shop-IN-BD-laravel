<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasAdminAreaWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Dashboard: the less-used reports (profit table, products, customers, payments, stock) in one folded section
 * with tabs, so the first screen stays calm. Only the open tab is loaded.
 */
class DashboardDetails extends Widget
{
    use HasAdminAreaWidget;
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.dashboard-details';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public bool $open = false;

    public string $tab = 'profit';

    public const TABS = [
        'profit' => 'লাভ-লোকসান',
        'products' => 'পণ্য',
        'customers' => 'কাস্টমার',
        'payments' => 'পেমেন্ট',
        'stock' => 'স্টক',
    ];

    public const WIDGETS = [
        'profit' => SalesProfitReport::class,
        'products' => TopProducts::class,
        'customers' => CustomerStats::class,
        'payments' => PaymentDeliveryReport::class,
        'stock' => LowStockProducts::class,
    ];

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function setTab(string $tab): void
    {
        if (array_key_exists($tab, self::TABS)) {
            $this->tab = $tab;
        }
    }

    protected function getViewData(): array
    {
        return ['tabs' => self::TABS, 'widget' => (($w = self::WIDGETS[$this->tab] ?? null) && $w::canView()) ? $w : null];
    }
}
