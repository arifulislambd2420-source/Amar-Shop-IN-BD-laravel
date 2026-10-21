<?php

namespace App\Filament\Resources\Coupons;

use App\Filament\Support\RowMenu;
use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use App\Models\Coupon;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class CouponResource extends Resource
{
    use HasAdminArea;

    protected static ?string $model = Coupon::class;

    public static function getModelLabel(): string
    {

        return Str::singular(__('কুপন'));

    }

    public static function getPluralModelLabel(): string
    {

        return __('কুপন');

    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    public static function getNavigationGroup(): string|UnitEnum|null
    {

        return __('মার্কেটিং');

    }

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->helperText(__('গ্রাহক ছোট বা বড় হাতের অক্ষরে লিখতে পারবেন।'))
                    ->unique(ignoreRecord: true),
                Select::make('discount_type')
                    ->options([
                        'percent' => __('শতকরা (%)'),
                        'fixed' => __('Fixed (৳)'),
                    ])
                    ->default('percent')
                    ->required(),
                TextInput::make('discount_value')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                TextInput::make('min_spend')
                    ->label(__('ন্যূনতম কেনাকাটা'))
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                TextInput::make('max_uses')
                    ->label(__('সর্বোচ্চ ব্যবহার'))
                    ->numeric()
                    ->minValue(0)
                    ->helperText(__('খালি = সীমাহীন।')),
                DateTimePicker::make('valid_until'),
                Toggle::make('is_active')
                    ->label(__('চালু'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('discount_type')
                    ->toggleable()
                    ->visibleFrom('md')
                    ->badge(),
                TextColumn::make('discount_value')
                    ->sortable(),
                TextColumn::make('min_spend')
                    ->toggleable()
                    ->visibleFrom('md')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('uses')
                    ->toggleable()
                    ->visibleFrom('md')
                    ->label(__('ব্যবহৃত'))
                    ->sortable(),
                TextColumn::make('max_uses')
                    ->toggleable()
                    ->visibleFrom('md')
                    ->label(__('সর্বোচ্চ'))
                    ->placeholder('∞'),
                TextColumn::make('valid_until')
                    ->toggleable()
                    ->visibleFrom('md')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                RowMenu::make([DeleteAction::make()]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCoupons::route('/'),
        ];
    }
}
