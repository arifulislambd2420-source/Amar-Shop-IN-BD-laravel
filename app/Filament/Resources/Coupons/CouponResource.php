<?php

namespace App\Filament\Resources\Coupons;

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
    use \App\Filament\Concerns\HasAdminArea;

    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('discount_type')
                    ->options([
                        'percent' => 'Percent (%)',
                        'fixed' => 'Fixed (৳)',
                    ])
                    ->default('percent')
                    ->required(),
                TextInput::make('discount_value')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                TextInput::make('min_spend')
                    ->label('Minimum spend')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                TextInput::make('max_uses')
                    ->label('Max uses')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Leave blank for unlimited.'),
                DateTimePicker::make('valid_until'),
                Toggle::make('is_active')
                    ->label('Active')
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
                    ->badge(),
                TextColumn::make('discount_value')
                    ->sortable(),
                TextColumn::make('min_spend')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('uses')
                    ->label('Used')
                    ->sortable(),
                TextColumn::make('max_uses')
                    ->label('Max')
                    ->placeholder('∞'),
                TextColumn::make('valid_until')
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
