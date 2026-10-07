<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dashboard "latest orders" table widget.
 */
class LatestOrders extends TableWidget
{
    use \App\Filament\Concerns\HasAdminAreaWidget;

    protected static string $adminArea = \App\Support\AdminAccess::AREA_ORDERS;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('সর্বশেষ অর্ডার')
            ->query(
                fn (): Builder => Order::query()->latest('created_at')
            )
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('invoice_no')
                    ->label('Invoice')
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('phone'),
                TextColumn::make('total')
                    ->formatStateUsing(fn ($state): string => '৳ '.number_format((float) $state, 2))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => OrderResource::STATUS_OPTIONS[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => OrderResource::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]));
    }
}
