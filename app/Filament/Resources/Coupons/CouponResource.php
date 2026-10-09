<?php

namespace App\Filament\Resources\Coupons;

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
use UnitEnum;

class CouponResource extends Resource
{
    use HasAdminArea;

    protected static ?string $model = Coupon::class;

    protected static ?string $modelLabel = 'কুপন';

    protected static ?string $pluralModelLabel = 'কুপন';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'মার্কেটিং';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->helperText('গ্রাহক ছোট বা বড় হাতের অক্ষরে লিখতে পারবেন।')
                    ->unique(ignoreRecord: true),
                Select::make('discount_type')
                    ->options([
                        'percent' => 'শতকরা (%)',
                        'fixed' => 'Fixed (৳)',
                    ])
                    ->default('percent')
                    ->required(),
                TextInput::make('discount_value')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                TextInput::make('min_spend')
                    ->label('ন্যূনতম কেনাকাটা')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                TextInput::make('max_uses')
                    ->label('সর্বোচ্চ ব্যবহার')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('খালি = সীমাহীন।'),
                DateTimePicker::make('valid_until'),
                Toggle::make('is_active')
                    ->label('চালু')
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
                    ->visibleFrom('md')
                    ->badge(),
                TextColumn::make('discount_value')
                    ->sortable(),
                TextColumn::make('min_spend')
                    ->visibleFrom('md')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('uses')
                    ->visibleFrom('md')
                    ->label('ব্যবহৃত')
                    ->sortable(),
                TextColumn::make('max_uses')
                    ->visibleFrom('md')
                    ->label('সর্বোচ্চ')
                    ->placeholder('∞'),
                TextColumn::make('valid_until')
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
                EditAction::make(),
                DeleteAction::make(),
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
