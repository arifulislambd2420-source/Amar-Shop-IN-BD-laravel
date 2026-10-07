<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasAdminAreaWidget;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Widgets\Widget;

/**
 * Dashboard banner at the very top: shown only when some product's stock is at
 * or below the low-stock threshold (Site Setting → low_stock_threshold).
 */
class LowStockAlert extends Widget
{
    use HasAdminAreaWidget;

    protected string $view = 'filament.widgets.low-stock-alert';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return parent::canView() && Product::query()->where('stock', '<=', Product::lowStockThreshold())->exists();
    }

    protected function getViewData(): array
    {
        $threshold = Product::lowStockThreshold();

        return [
            'threshold' => $threshold,
            'out' => Product::query()->where('stock', '<=', 0)->count(),
            'low' => Product::query()->where('stock', '>', 0)->where('stock', '<=', $threshold)->count(),
            'url' => ProductResource::getUrl('index'),
        ];
    }
}
