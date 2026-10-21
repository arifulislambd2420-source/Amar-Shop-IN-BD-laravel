<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasAdminAreaWidget;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\AdminAccess;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dashboard "latest orders" table widget.
 */
class LatestOrders extends TableWidget
{
    use HasAdminAreaWidget;

    protected static string $adminArea = AdminAccess::AREA_ORDERS;

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('সর্বশেষ অর্ডার'))
            ->query(
                fn (): Builder => Order::query()->latest('created_at')
            )
            ->defaultPaginationPageOption(10)->paginationPageOptions([5, 10, 25])
            ->columns([
                TextColumn::make('invoice_no')
                    ->label(__('ইনভয়েস'))
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->label(__('গ্রাহক'))
                    ->searchable(),
                TextColumn::make('phone')->label(__('ফোন')),
                TextColumn::make('total')
                    ->formatStateUsing(fn ($state): string => '৳ '.number_format((float) $state, 2))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => OrderResource::statuses()[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => OrderResource::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions(OrderResource::quickStatusActions())
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]));
    }
}
