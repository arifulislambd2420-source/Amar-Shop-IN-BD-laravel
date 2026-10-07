<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Dashboard: products that are out of stock or almost (≤ 5), lowest first. */
class LowStockProducts extends TableWidget
{
    use \App\Filament\Concerns\HasAdminAreaWidget;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('কম স্টকের প্রোডাক্ট')
            ->description('স্টক '.Product::lowStockThreshold().' বা তার কম')
            ->query(fn (): Builder => Product::query()
                ->where('stock', '<=', Product::lowStockThreshold())
                ->orderBy('stock')
                ->orderBy('name'))
            ->defaultPaginationPageOption(5)
            ->paginationPageOptions([5, 10])
            ->emptyStateHeading('সব প্রোডাক্টের স্টক ঠিক আছে')
            ->columns([
                TextColumn::make('name')
                    ->label('প্রোডাক্ট')
                    ->limit(32)
                    ->searchable(),
                TextColumn::make('stock')
                    ->label('স্টক')
                    ->badge()
                    ->color(fn (int $state): string => $state <= 0 ? 'danger' : 'warning')
                    ->formatStateUsing(fn (int $state): string => $state <= 0 ? 'শেষ' : (string) $state)
                    ->sortable(),
            ])
            ->recordUrl(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record]));
    }
}
