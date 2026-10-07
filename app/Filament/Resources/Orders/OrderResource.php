<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\ItemsRelationManager;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OrderResource extends Resource
{
    use \App\Filament\Concerns\HasAdminArea;

    protected static string $adminArea = \App\Support\AdminAccess::AREA_ORDERS;

    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'invoice_no';

    public const STATUS_OPTIONS = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Delivered',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        // Set by OrderService::finalizeBkashPayment when bKash payment
        // succeeded but stock ran out in the meantime — needs manual review.
        'on_hold' => 'On hold (needs review)',
    ];

    public const PAYMENT_METHOD_OPTIONS = [
        'cod' => 'Cash on Delivery (COD)',
        'advance' => 'Advance Delivery Charge',
        'bkash' => 'bKash',
        'sslcommerz' => 'SSLCommerz (Card/Bank)',
    ];

    public const PAYMENT_STATUS_OPTIONS = [
        'unpaid' => 'Unpaid',
        'advance_paid' => 'Advance Paid',
        'paid' => 'Paid (Full)',
        'failed' => 'Failed',
    ];

    public const STATUS_COLORS = [
        'pending' => 'warning',
        'processing' => 'info',
        'shipped' => 'primary',
        'out_for_delivery' => 'info',
        'delivered' => 'success',
        'completed' => 'success',
        'cancelled' => 'danger',
        'on_hold' => 'danger',
    ];

    /** Orders still waiting for an admin to review a fraud flag. */
    public static function getNavigationBadge(): ?string
    {
        $count = Order::where('is_flagged', true)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Flagged orders to review';
    }

    public static function canCreate(): bool
    {
        // Orders are created by the storefront checkout, not in the admin panel.
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order')
                    ->columns(3)
                    ->schema([
                        TextInput::make('invoice_no')
                            ->label('Invoice no')
                            ->disabled(),
                        TextInput::make('order_token')
                            ->label('Order token')
                            ->disabled(),
                        TextInput::make('created_at')
                            ->label('Created at')
                            ->disabled(),
                    ]),
                Section::make('Customer')
                    ->columns(2)
                    ->schema([
                        TextInput::make('customer_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('district')
                            ->maxLength(255),
                        TextInput::make('thana')
                            ->maxLength(255),
                        TextInput::make('postcode')
                            ->maxLength(255),
                        Textarea::make('address')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('Payment & status')
                    ->columns(2)
                    ->schema([
                        Select::make('payment_method')
                            ->options(self::PAYMENT_METHOD_OPTIONS),
                        Select::make('payment_status')
                            ->options(self::PAYMENT_STATUS_OPTIONS),
                        Select::make('status')
                            ->options(self::STATUS_OPTIONS)
                            ->required(),
                        TextInput::make('advance_amount')
                            ->numeric()
                            ->prefix('৳')
                            ->default(0),
                        TextInput::make('transaction_id')
                            ->label('Transaction ID (bKash)')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
                Section::make('Fraud review')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_flagged')
                            ->label('Flagged for review')
                            ->helperText('Turn off once you have checked this order (a COD order then gets its confirmation SMS).')
                            ->columnSpanFull(),
                        Textarea::make('flag_reason')
                            ->label('Why it was flagged')
                            ->disabled()
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('ip_address')
                            ->label('Customer IP')
                            ->disabled(),
                    ]),
                Section::make('Totals')
                    ->columns(4)
                    ->schema([
                        TextInput::make('subtotal')
                            ->numeric()
                            ->prefix('৳'),
                        TextInput::make('shipping_fee')
                            ->numeric()
                            ->prefix('৳')
                            ->default(0),
                        TextInput::make('discount')
                            ->numeric()
                            ->prefix('৳')
                            ->default(0),
                        TextInput::make('total')
                            ->numeric()
                            ->prefix('৳'),
                    ]),
                Section::make('Courier')
                    ->columns(3)
                    ->schema([
                        TextInput::make('consignment_id')
                            ->label('Consignment ID')
                            ->maxLength(255),
                        TextInput::make('tracking_code')
                            ->maxLength(255),
                        TextInput::make('courier_status')
                            ->maxLength(255),
                        Textarea::make('courier_error')
                            ->label('Last courier error')
                            ->rows(2)
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?\Illuminate\Database\Eloquent\Model $record): bool => filled($record?->courier_error))
                            ->columnSpanFull(),
                    ]),
                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_no')
                    ->label('Invoice')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Order $record): string => '#' . $record->id)
                    ->color(fn (Order $record): ?string => $record->is_flagged ? 'danger' : null)
                    ->weight(fn (Order $record): ?FontWeight => $record->is_flagged ? FontWeight::Bold : null),
                TextColumn::make('is_flagged')
                    ->label('Review')
                    ->badge()
                    ->state(fn (Order $record): ?string => $record->is_flagged ? 'Flagged' : null)
                    ->color('danger')
                    ->icon(Heroicon::OutlinedFlag)
                    ->tooltip(fn (Order $record): ?string => $record->is_flagged ? $record->flag_reason : null)
                    ->wrap()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('total')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::STATUS_OPTIONS[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => self::STATUS_COLORS[$state] ?? 'gray')
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::PAYMENT_STATUS_OPTIONS[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'paid' => 'success',
                        'advance_paid' => 'info',
                        default => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(self::STATUS_OPTIONS),
                SelectFilter::make('payment_status')
                    ->options(self::PAYMENT_STATUS_OPTIONS),
                TernaryFilter::make('is_flagged')
                    ->label('Fraud review')
                    ->placeholder('All orders')
                    ->trueLabel('Flagged only')
                    ->falseLabel('Not flagged'),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                Action::make('clearFlag')
                    ->label('Mark reviewed')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Order $record): bool => $record->is_flagged)
                    ->modalDescription(fn (Order $record): string => (string) $record->flag_reason)
                    ->requiresConfirmation()
                    ->action(fn (Order $record) => $record->update(['is_flagged' => false])),
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
