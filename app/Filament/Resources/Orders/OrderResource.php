<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\ItemsRelationManager;
use App\Models\Order;
use App\Support\AdminAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class OrderResource extends Resource
{
    use HasAdminArea;

    protected static string $adminArea = AdminAccess::AREA_ORDERS;

    protected static ?string $model = Order::class;

    protected static ?string $modelLabel = 'অর্ডার';

    protected static ?string $pluralModelLabel = 'অর্ডার';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'বিক্রয়';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'invoice_no';

    public const STATUS_OPTIONS = [
        'pending' => 'অপেক্ষমাণ',
        'processing' => 'কনফার্মড',
        'shipped' => 'শিপড',
        'out_for_delivery' => 'ডেলিভারির পথে',
        'delivered' => 'ডেলিভারড',
        'completed' => 'সম্পন্ন',
        'cancelled' => 'বাতিল',
        // Set by OrderService::finalizeBkashPayment when bKash payment
        // succeeded but stock ran out in the meantime — needs manual review.
        'on_hold' => 'যাচাই চলছে (দেখা দরকার)',
    ];

    public const PAYMENT_METHOD_OPTIONS = [
        'cod' => 'ক্যাশ অন ডেলিভারি (COD)',
        'advance' => 'অগ্রিম ডেলিভারি চার্জ',
        'bkash' => 'বিকাশ',
        'sslcommerz' => 'SSLCommerz (কার্ড/ব্যাংক)',
    ];

    public const PAYMENT_STATUS_OPTIONS = [
        'unpaid' => 'অপরিশোধিত',
        'advance_paid' => 'অগ্রিম পরিশোধিত',
        'paid' => 'পুরো পরিশোধিত',
        'failed' => 'ব্যর্থ',
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
        return 'যাচাই করার মতো সন্দেহজনক অর্ডার';
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
                Section::make('অর্ডার')
                    ->columns(3)
                    ->schema([
                        TextInput::make('invoice_no')
                            ->label('ইনভয়েস নম্বর')
                            ->disabled(),
                        TextInput::make('order_token')
                            ->label('অর্ডার টোকেন')
                            ->disabled(),
                        TextInput::make('created_at')
                            ->label('তৈরির সময়')
                            ->disabled(),
                    ]),
                Section::make('গ্রাহক')
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
                Section::make('পেমেন্ট ও স্ট্যাটাস')
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
                            ->label('ট্রানজাকশন আইডি (বিকাশ)')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
                Section::make('সন্দেহজনক অর্ডার যাচাই')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_flagged')
                            ->label('যাচাইয়ের জন্য চিহ্নিত')
                            ->helperText('অর্ডারটি যাচাই করা হলে বন্ধ করুন (COD অর্ডারে তখন কনফার্মেশন এসএমএস যাবে)।')
                            ->columnSpanFull(),
                        Textarea::make('flag_reason')
                            ->label('কেন চিহ্নিত হলো')
                            ->disabled()
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('ip_address')
                            ->label('গ্রাহকের আইপি')
                            ->disabled(),
                    ]),
                Section::make('মোট হিসাব')
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
                Section::make('কুরিয়ার')
                    ->columns(3)
                    ->schema([
                        TextInput::make('consignment_id')
                            ->label('কনসাইনমেন্ট আইডি')
                            ->maxLength(255),
                        TextInput::make('tracking_code')
                            ->maxLength(255),
                        TextInput::make('courier_status')
                            ->maxLength(255),
                        Textarea::make('courier_error')
                            ->label('কুরিয়ারের সর্বশেষ ত্রুটি')
                            ->rows(2)
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?Model $record): bool => filled($record?->courier_error))
                            ->columnSpanFull(),
                    ]),
                Section::make('নোট')
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
                    ->label('ইনভয়েস')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Order $record): string => '#'.$record->id.' · '.$record->created_at?->format('d M, h:i A'))
                    ->color(fn (Order $record): ?string => $record->is_flagged ? 'danger' : null)
                    ->weight(fn (Order $record): ?FontWeight => $record->is_flagged ? FontWeight::Bold : null),
                TextColumn::make('is_flagged')
                    ->visibleFrom('md')
                    ->label('যাচাই')
                    ->badge()
                    ->state(fn (Order $record): ?string => $record->is_flagged ? 'চিহ্নিত' : null)
                    ->color('danger')
                    ->icon(Heroicon::OutlinedFlag)
                    ->tooltip(fn (Order $record): ?string => $record->is_flagged ? $record->flag_reason : null)
                    ->wrap()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->description(fn (Order $record): string => (string) $record->phone)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->visibleFrom('md')
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
                    ->visibleFrom('lg')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::PAYMENT_STATUS_OPTIONS[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'paid' => 'success',
                        'advance_paid' => 'info',
                        default => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->visibleFrom('md')
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
                    ->label('সন্দেহজনক অর্ডার যাচাই')
                    ->placeholder('সব অর্ডার')
                    ->trueLabel('শুধু চিহ্নিত')
                    ->falseLabel('চিহ্নিত নয়'),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label('থেকে'),
                        DatePicker::make('until')->label('পর্যন্ত'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                Action::make('clearFlag')
                    ->label('যাচাই হয়েছে')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Order $record): bool => $record->is_flagged)
                    ->modalDescription(fn (Order $record): string => (string) $record->flag_reason)
                    ->requiresConfirmation()
                    ->action(fn (Order $record) => $record->update(['is_flagged' => false])),
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
                // Only a cancelled order can be deleted (see Order::canBeDeleted).
                DeleteAction::make()->iconButton()->visible(fn (Order $record): bool => $record->canBeDeleted()),
            ])
            ->toolbarActions([
                BulkAction::make('deleteCancelled')
                    ->label('বাতিল অর্ডার মুছুন')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('বাছাই করা বাতিল অর্ডারগুলো মুছবেন?')
                    ->modalDescription('শুধু বাতিল করা অর্ডার মোছা হয়। অন্য বাছাই করা অর্ডার যেমন আছে তেমনই থাকবে — মুছতে চাইলে আগে বাতিল করুন।')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        [$cancelled, $kept] = $records->partition(fn (Order $order): bool => $order->canBeDeleted());
                        $cancelled->each->delete();

                        Notification::make()
                            ->title($cancelled->count().' cancelled order(s) deleted')
                            ->body($kept->isNotEmpty() ? $kept->count().' order(s) were not cancelled, so they were kept.' : null)
                            ->success()
                            ->send();
                    }),
            ]);
    }

    /** Deleting is only for cancelled orders; a live order must be cancelled first. */
    public static function canDelete(Model $record): bool
    {
        return $record instanceof Order && $record->canBeDeleted() && parent::canDelete($record);
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
