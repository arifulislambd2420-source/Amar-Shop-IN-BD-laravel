<?php

namespace App\Filament\Resources\IncompleteOrders;

use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\IncompleteOrders\Pages\ManageIncompleteOrders;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\IncompleteOrder;
use App\Support\AdminAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use RuntimeException;
use UnitEnum;

/**
 * Visitors who typed a phone number at checkout / on a landing page but never
 * ordered. Call them, then "Convert to order" to create the real order.
 */
class IncompleteOrderResource extends Resource
{
    use HasAdminArea;

    protected static string $adminArea = AdminAccess::AREA_ORDERS;

    protected static ?string $model = IncompleteOrder::class;

    public static function getPluralModelLabel(): string
    {

        return __('অসম্পূর্ণ অর্ডার');

    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    public static function getNavigationGroup(): string|UnitEnum|null
    {

        return __('বিক্রয়');

    }

    public static function getNavigationLabel(): string
    {

        return __('অসম্পূর্ণ অর্ডার');

    }

    public static function getModelLabel(): string
    {

        return Str::singular(__('অসম্পূর্ণ অর্ডার'));

    }

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = IncompleteOrder::where('status', IncompleteOrder::OPEN)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('landingPage'))
            ->columns([
                TextColumn::make('name')->label(__('নাম'))->placeholder('—')->searchable(),
                TextColumn::make('phone')->label(__('ফোন'))->searchable()->copyable(),
                TextColumn::make('items')
                    ->label(__('প্রোডাক্ট'))
                    ->state(fn (IncompleteOrder $r): string => collect($r->items)->map(fn ($i) => ($i['name'] ?? '').' × '.($i['quantity'] ?? 1))->implode(', '))
                    ->wrap()
                    ->limit(60),
                TextColumn::make('total')->label(__('মোট'))->formatStateUsing(fn ($state): string => '৳ '.number_format((float) $state, 2)),
                TextColumn::make('source')
                    ->label(__('কোথা থেকে'))
                    ->state(fn (IncompleteOrder $r): string => $r->source === 'landing' ? __('ল্যান্ডিং: :title', ['title' => $r->landingPage?->title ?? '—']) : __('চেকআউট'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->label(__('স্ট্যাটাস'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        IncompleteOrder::CONVERTED => __('অর্ডার হয়েছে'),
                        IncompleteOrder::DISMISSED => __('বাদ'),
                        default => __('অসম্পূর্ণ'),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        IncompleteOrder::CONVERTED => 'success',
                        IncompleteOrder::DISMISSED => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('updated_at')->label(__('সময়'))->since()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        IncompleteOrder::OPEN => __('অসম্পূর্ণ'),
                        IncompleteOrder::CONVERTED => __('অর্ডার হয়েছে'),
                        IncompleteOrder::DISMISSED => __('বাদ'),
                    ])
                    ->default(IncompleteOrder::OPEN),
            ])
            ->recordActions([
                self::convertAction(),
                DeleteAction::make()->label(__('বাদ দিন'))->visible(fn (IncompleteOrder $r): bool => $r->status === IncompleteOrder::OPEN),
            ]);
    }

    public static function convertAction(): Action
    {
        return Action::make('convert')
            ->label(__('অর্ডারে রূপান্তর'))
            ->icon(Heroicon::OutlinedArrowRightCircle)
            ->color('success')
            ->visible(fn (IncompleteOrder $r): bool => $r->status === IncompleteOrder::OPEN)
            ->modalHeading(__('অর্ডারে রূপান্তর'))
            ->modalDescription(__('গ্রাহকের সাথে কথা বলে ডেলিভারির তথ্য নিশ্চিত করুন। অর্ডার ক্যাশ অন ডেলিভারিতে তৈরি হবে।'))
            ->fillForm(fn (IncompleteOrder $r): array => [
                'customer_name' => $r->name,
                'phone' => $r->phone,
                'district' => $r->district,
                'address' => $r->address,
            ])
            ->schema([
                TextInput::make('customer_name')->label(__('নাম'))->required()->maxLength(255),
                TextInput::make('phone')->label(__('ফোন'))->required()->maxLength(20),
                Select::make('district')->label(__('জেলা'))->options(fn () => array_combine(config('districts'), config('districts')))->searchable()->required(),
                TextInput::make('thana')->label(__('থানা / উপজেলা'))->required()->maxLength(100),
                Textarea::make('address')->label(__('বিস্তারিত ঠিকানা'))->required()->rows(2)->maxLength(1000),
            ])
            ->action(function (IncompleteOrder $record, array $data) {
                try {
                    $order = $record->convertToOrder($data);
                } catch (RuntimeException $e) {
                    Notification::make()->title(__('অর্ডার তৈরি করা যায়নি'))->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title(__('অর্ডার তৈরি হয়েছে: :invoice', ['invoice' => $order->invoice_no]))
                    ->success()
                    ->actions([
                        Action::make('view')->label(__('দেখুন'))->url(OrderResource::getUrl('view', ['record' => $order])),
                    ])
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageIncompleteOrders::route('/'),
        ];
    }
}
