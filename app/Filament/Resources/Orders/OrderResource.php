<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Support\RowMenu;
use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\ItemsRelationManager;
use App\Models\Order;
use App\Services\Tracking\ServerPurchase;
use App\Support\AdminAccess;
use App\Support\Money;
use App\Support\TrackingSettings;
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
use Filament\Schemas\Components\Text;
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
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

class OrderResource extends Resource
{
    use HasAdminArea;

    protected static string $adminArea = AdminAccess::AREA_ORDERS;

    protected static ?string $model = Order::class;

    public static function getModelLabel(): string
    {

        return Str::singular(__('অর্ডার'));

    }

    public static function getPluralModelLabel(): string
    {

        return __('অর্ডার');

    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    public static function getNavigationGroup(): string|UnitEnum|null
    {

        return __('বিক্রয়');

    }

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

    /** Labels in the admin's language (the constants above are the Bangla source text). */
    public static function statuses(): array
    {
        return array_map('__', self::STATUS_OPTIONS);
    }

    public static function paymentMethods(): array
    {
        return array_map('__', self::PAYMENT_METHOD_OPTIONS);
    }

    public static function paymentStatuses(): array
    {
        return array_map('__', self::PAYMENT_STATUS_OPTIONS);
    }

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
        return __('যাচাই করার মতো সন্দেহজনক অর্ডার');
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
                Section::make(__('অর্ডার'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('invoice_no')
                            ->label(__('ইনভয়েস নম্বর'))
                            ->disabled(),
                        TextInput::make('order_token')
                            ->label(__('অর্ডার টোকেন'))
                            ->disabled(),
                        TextInput::make('created_at')
                            ->label(__('তৈরির সময়'))
                            ->disabled(),
                    ]),
                Section::make(__('গ্রাহক'))
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
                Section::make(__('পেমেন্ট ও স্ট্যাটাস'))
                    ->columns(2)
                    ->schema([
                        Select::make('payment_method')
                            ->options(self::paymentMethods()),
                        Select::make('payment_status')
                            ->options(self::paymentStatuses()),
                        Select::make('status')
                            ->options(self::statuses())
                            ->required(),
                        TextInput::make('advance_amount')
                            ->numeric()
                            ->prefix(__('৳'))
                            ->default(0),
                        TextInput::make('transaction_id')
                            ->label(__('ট্রানজাকশন আইডি (বিকাশ)'))
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
                Section::make(__('সন্দেহজনক অর্ডার যাচাই'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_flagged')
                            ->label(__('যাচাইয়ের জন্য চিহ্নিত'))
                            ->helperText(__('অর্ডারটি যাচাই করা হলে বন্ধ করুন (COD অর্ডারে তখন কনফার্মেশন এসএমএস যাবে)।'))
                            ->columnSpanFull(),
                        Textarea::make('flag_reason')
                            ->label(__('কেন চিহ্নিত হলো'))
                            ->disabled()
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('ip_address')
                            ->label(__('গ্রাহকের আইপি'))
                            ->disabled(),
                    ]),
                Section::make(__('সার্ভার থেকে Purchase (ট্র্যাকিং)'))
                    ->description(__('অর্ডার কনফার্ম হলে সার্ভার Meta ও GA4-কে Purchase জানায়। ফল এখানে; ব্যর্থ হলে কারণ দেখায় এবং উপরের "আবার পাঠান" বাটন আছে।'))
                    ->columns(2)
                    ->schema([
                        Text::make(fn (?Order $record): string => 'Meta (Conversions API): '.self::purchaseLine($record, 'capi'))
                            ->color(fn (?Order $record): ?string => self::purchaseColor($record, 'capi')),
                        Text::make(fn (?Order $record): string => 'Google Analytics 4: '.self::purchaseLine($record, 'ga4'))
                            ->color(fn (?Order $record): ?string => self::purchaseColor($record, 'ga4')),
                    ]),
                Section::make(__('মোট হিসাব'))
                    ->columns(4)
                    ->schema([
                        TextInput::make('subtotal')
                            ->numeric()
                            ->prefix(__('৳')),
                        TextInput::make('shipping_fee')
                            ->numeric()
                            ->prefix(__('৳'))
                            ->default(0),
                        TextInput::make('discount')
                            ->numeric()
                            ->prefix(__('৳'))
                            ->default(0),
                        TextInput::make('total')
                            ->numeric()
                            ->prefix(__('৳')),
                    ]),
                Section::make(__('কুরিয়ার'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('consignment_id')
                            ->label(__('কনসাইনমেন্ট আইডি'))
                            ->maxLength(255),
                        TextInput::make('tracking_code')
                            ->maxLength(255),
                        TextInput::make('courier_status')
                            ->maxLength(255),
                        Textarea::make('courier_error')
                            ->label(__('কুরিয়ারের সর্বশেষ ত্রুটি'))
                            ->rows(2)
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?Model $record): bool => filled($record?->courier_error))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('নোট'))
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
                    ->label(__('ইনভয়েস'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Order $record): string => '#'.$record->id.' · '.$record->created_at?->format('d M, h:i A'))
                    ->color(fn (Order $record): ?string => $record->is_flagged ? 'danger' : null)
                    ->weight(fn (Order $record): ?FontWeight => $record->is_flagged ? FontWeight::Bold : null),
                TextColumn::make('is_flagged')
                    ->visibleFrom('md')
                    ->toggleable()
                    ->label(__('যাচাই'))
                    ->badge()
                    ->state(fn (Order $record): ?string => $record->is_flagged ? __('চিহ্নিত') : null)
                    ->color('danger')
                    ->icon(Heroicon::OutlinedFlag)
                    ->tooltip(fn (Order $record): ?string => $record->is_flagged ? $record->flag_reason : null)
                    ->wrap()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->description(fn (Order $record): string => (string) $record->phone)
                    ->searchable()
                    ->sortable(),
                // (the phone is also under the customer's name; this column is for sorting / copying)
                TextColumn::make('phone')
                    ->visibleFrom('md')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total')
                    ->formatStateUsing(fn ($state): string => Money::taka((float) $state))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::statuses()[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => self::STATUS_COLORS[$state] ?? 'gray')
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->visibleFrom('lg')
                    ->toggleable()
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::paymentStatuses()[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'paid' => 'success',
                        'advance_paid' => 'info',
                        default => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->visibleFrom('md')
                    ->dateTime('d M, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(self::statuses()),
                SelectFilter::make('payment_status')
                    ->options(self::paymentStatuses()),
                TernaryFilter::make('is_flagged')
                    ->label(__('সন্দেহজনক অর্ডার যাচাই'))
                    ->placeholder(__('সব অর্ডার'))
                    ->trueLabel(__('শুধু চিহ্নিত'))
                    ->falseLabel(__('চিহ্নিত নয়')),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label(__('থেকে')),
                        DatePicker::make('until')->label(__('পর্যন্ত')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
                RowMenu::make([
                    Action::make('clearFlag')
                        ->label(__('যাচাই হয়েছে'))
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->visible(fn (Order $record): bool => $record->is_flagged)
                        ->modalDescription(fn (Order $record): string => (string) $record->flag_reason)
                        ->requiresConfirmation()
                        ->action(fn (Order $record) => $record->update(['is_flagged' => false])),
                    ...self::quickStatusActions(inMenu: true),
                    // Any order, super admins only; the popup spells out what deleting does.
                    self::configureDelete(DeleteAction::make()),
                ]),
            ])
            ->toolbarActions([
                BulkAction::make('deleteOrders')
                    ->label(__('অর্ডার মুছুন'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn (): bool => self::canDeleteAny())
                    ->authorize(fn (): bool => self::canDeleteAny())
                    ->requiresConfirmation()
                    ->modalHeading(__('বাছাই করা অর্ডারগুলো মুছবেন?'))
                    ->modalDescription(fn (Collection $records): HtmlString => self::bulkDeleteDescription($records))
                    ->modalSubmitActionLabel(__('হ্যাঁ, মুছুন'))
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        abort_unless(self::canDeleteAny(), 403);

                        $records->each->delete();

                        Notification::make()
                            ->title(__(':count টি অর্ডার মোছা হয়েছে', ['count' => $records->count()]))
                            ->success()
                            ->send();
                    }),
            ]);
    }

    /** Any order can be deleted, but only by a super admin. */
    public static function canDelete(Model $record): bool
    {
        return $record instanceof Order && self::canDeleteAny();
    }

    public static function canDeleteAny(): bool
    {
        return (bool) auth('admin')->user()?->isSuperAdmin();
    }

    // ── Quick status buttons (orders list and dashboard) ─────────────────

    /**
     * Confirm / Cancel / Delivered in one click (with a confirmation). They change the status like the edit form
     * does, so everything that follows a status — stock back on cancel, customer SMS, and the server-side Purchase
     * on Confirmed — happens exactly the same.
     *
     * @return array<int, Action>
     */
    public static function quickStatusActions(bool $inMenu = false): array
    {
        $button = fn (Action $action): Action => $inMenu ? $action : $action->iconButton()->tooltip($action->getLabel());

        $move = function (Order $record, string $status, string $done): void {
            $record->update(['status' => $status]);

            Notification::make()->title(__($done, ['invoice' => $record->invoice_no]))->success()->send();
        };

        return array_map($button, [
            Action::make('quickConfirm')
                ->label(__('কনফার্ম করুন'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (Order $record): bool => $record->status === 'pending')
                ->requiresConfirmation()
                ->modalHeading(fn (Order $record): string => __(':invoice কনফার্ম করবেন?', ['invoice' => $record->invoice_no]))
                ->modalDescription(__('কনফার্ম হলে এটি প্রকৃত বিক্রি হিসেবে গোনা হবে, কাস্টমারকে এসএমএস যাবে এবং (সেট করা থাকলে) Meta ও GA4-তে Purchase যাবে।'))
                ->action(fn (Order $record) => $move($record, 'processing', ':invoice কনফার্ম হয়েছে।')),

            Action::make('quickDelivered')
                ->label(__('ডেলিভারড'))
                ->icon(Heroicon::OutlinedTruck)
                ->color('info')
                ->visible(fn (Order $record): bool => in_array($record->status, ['processing', 'shipped', 'out_for_delivery'], true))
                ->requiresConfirmation()
                ->modalHeading(fn (Order $record): string => __(':invoice ডেলিভারড করবেন?', ['invoice' => $record->invoice_no]))
                ->action(fn (Order $record) => $move($record, 'delivered', ':invoice ডেলিভারড হয়েছে।')),

            Action::make('quickCancel')
                ->label(__('বাতিল করুন'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (Order $record): bool => ! in_array($record->status, ['cancelled', 'delivered', 'completed'], true))
                ->requiresConfirmation()
                ->modalHeading(fn (Order $record): string => __(':invoice বাতিল করবেন?', ['invoice' => $record->invoice_no]))
                ->modalDescription(__('বাতিল হলে অর্ডারের স্টক ফেরত আসবে এবং এটি প্রকৃত বিক্রির হিসাবে থাকবে না।'))
                ->action(fn (Order $record) => $move($record, 'cancelled', ':invoice বাতিল হয়েছে।')),
        ]);
    }

    // ── Server-side Purchase (Meta Conversions API / GA4) ────────────────

    private static function platformReady(string $platform): bool
    {
        return $platform === 'capi' ? TrackingSettings::capiReady() : TrackingSettings::ga4Ready();
    }

    /** One line: sent (when) / failed (why) / waiting / not yet / not set up. */
    public static function purchaseLine(?Order $order, string $platform): string
    {
        if (! $order) {
            return '';
        }

        $status = $order->{"{$platform}_status"};
        $error = $order->{"{$platform}_error"};
        $sentAt = $order->{"{$platform}_sent_at"};

        if ($status === 'sent') {
            return '✔ '.__('গেছে').($sentAt ? ' — '.$sentAt->format('d M Y, H:i') : '');
        }

        if ($status === 'failed') {
            return '✖ '.__('ব্যর্থ').($error ? ' — '.$error : '');
        }

        if (! self::platformReady($platform)) {
            return __('সেটআপ করা নেই (অ্যাডমিন → জিটিএম / পিক্সেল)');
        }

        return $order->purchase_triggered_at
            ? __('কিউতে আছে, শিগগিরই যাবে')
            : __('এখনো যায়নি — অর্ডার :status স্ট্যাটাসে পৌঁছালে যাবে', ['status' => self::statuses()[TrackingSettings::purchaseStatus()] ?? TrackingSettings::purchaseStatus()]);
    }

    public static function purchaseColor(?Order $order, string $platform): ?string
    {
        return match ($order?->{"{$platform}_status"}) {
            'sent' => 'success',
            'failed' => 'danger',
            default => null,
        };
    }

    /** "Send Purchase again": whatever is set up and has not gone out yet (failed or never sent). */
    public static function resendPurchaseAction(): Action
    {
        $pending = fn (Order $order): array => array_values(array_filter([
            self::platformReady('capi') && $order->capi_status !== 'sent' ? 'Meta' : null,
            self::platformReady('ga4') && $order->ga4_status !== 'sent' ? 'GA4' : null,
        ]));

        return Action::make('resendPurchase')
            ->label(__('Purchase আবার পাঠান'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Order $record): bool => ServerPurchase::sendable($record) && $pending($record) !== [])
            ->requiresConfirmation()
            ->modalHeading(__('Purchase আবার পাঠাবেন?'))
            ->modalDescription(fn (Order $record): string => __(':to-তে Purchase পাঠানো হবে। যেখানে আগেই গেছে সেখানে আবার যাবে না।', ['to' => implode(' + ', $pending($record))]))
            ->action(function (Order $record): void {
                ServerPurchase::resend($record);

                Notification::make()->title(__('Purchase কিউতে দেওয়া হয়েছে'))->body(__('কিছুক্ষণ পর পাতাটি রিফ্রেশ করে ফল দেখুন।'))->success()->send();
            });
    }

    /** The delete popup: order number, customer, total, status — and what else deleting does. */
    public static function configureDelete(DeleteAction $action): DeleteAction
    {
        return $action
            ->modalHeading(__('অর্ডার মুছবেন?'))
            ->modalDescription(fn (Order $record): HtmlString => self::deleteDescription($record))
            ->modalSubmitActionLabel(__('হ্যাঁ, মুছুন'));
    }

    public static function deleteDescription(Order $order): HtmlString
    {
        $lines = [
            '<strong>'.e(__('অর্ডার নম্বর')).':</strong> '.e($order->invoice_no ?: '#'.$order->id),
            '<strong>'.e(__('গ্রাহক')).':</strong> '.e($order->customer_name.' ('.$order->phone.')'),
            '<strong>'.e(__('মোট টাকা')).':</strong> '.e(Money::taka($order->total)),
            '<strong>'.e(__('স্ট্যাটাস')).':</strong> '.e(self::statuses()[$order->status] ?? $order->status),
        ];

        $notes = [];

        if ($order->status !== 'cancelled') {
            $notes[] = __('এই অর্ডার বাতিল হয়নি: মুছলে এর স্টক ফেরত আসবে এবং কুপন থাকলে কুপনের ব্যবহার এক কমবে।');
        }

        if (filled($order->consignment_id)) {
            $notes[] = '⚠ '.__('এই অর্ডার কুরিয়ারে পাঠানো হয়েছে (কনসাইনমেন্ট: :id)। মুছলে কুরিয়ারে বুকিং বাতিল হবে না — কুরিয়ারের নিজস্ব প্যানেল থেকে আলাদা করে বাতিল করুন।', ['id' => $order->consignment_id]);
        }

        $notes[] = __('মোছার পর অর্ডারটি আর ফেরানো যাবে না।');

        return new HtmlString(implode('<br>', $lines).'<br><br>'.implode('<br>', array_map('e', $notes)));
    }

    /** The same warning for a whole selection: counts, money, statuses, courier orders. */
    public static function bulkDeleteDescription(Collection $records): HtmlString
    {
        $statuses = self::statuses();
        $byStatus = $records->countBy('status')->map(fn ($n, $s) => ($statuses[$s] ?? $s).': '.$n)->implode(', ');
        $live = $records->where('status', '!=', 'cancelled')->count();
        $courier = $records->filter(fn (Order $o) => filled($o->consignment_id))->count();

        $lines = [
            '<strong>'.e(__('অর্ডার')).':</strong> '.e((string) $records->count()),
            '<strong>'.e(__('মোট টাকা')).':</strong> '.e(Money::taka($records->sum('total'))),
            '<strong>'.e(__('স্ট্যাটাস')).':</strong> '.e($byStatus),
        ];

        $notes = [];

        if ($live > 0) {
            $notes[] = __(':count টি অর্ডার বাতিল হয়নি: সেগুলোর স্টক ফেরত আসবে এবং কুপন থাকলে কুপনের ব্যবহার এক কমবে।', ['count' => $live]);
        }

        if ($courier > 0) {
            $notes[] = '⚠ '.__(':count টি অর্ডার কুরিয়ারে পাঠানো হয়েছে। মুছলে কুরিয়ারে বুকিং বাতিল হবে না — কুরিয়ারের নিজস্ব প্যানেল থেকে আলাদা করে বাতিল করুন।', ['count' => $courier]);
        }

        $notes[] = __('মোছার পর অর্ডারগুলো আর ফেরানো যাবে না।');

        return new HtmlString(implode('<br>', $lines).'<br><br>'.implode('<br>', array_map('e', $notes)));
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
