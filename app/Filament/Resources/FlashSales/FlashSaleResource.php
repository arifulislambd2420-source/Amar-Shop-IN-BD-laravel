<?php

namespace App\Filament\Resources\FlashSales;

use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\FlashSales\Pages\CreateFlashSale;
use App\Filament\Resources\FlashSales\Pages\EditFlashSale;
use App\Filament\Resources\FlashSales\Pages\ListFlashSales;
use App\Filament\Resources\FlashSales\RelationManagers\ItemsRelationManager;
use App\Models\FlashSale;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
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

class FlashSaleResource extends Resource
{
    use HasAdminArea;

    protected static ?string $model = FlashSale::class;

    protected static ?string $modelLabel = 'ফ্ল্যাশ সেল';

    protected static ?string $pluralModelLabel = 'ফ্ল্যাশ সেল';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'মার্কেটিং';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                DateTimePicker::make('end_time')
                    ->label('শেষ হবে')
                    ->required(),
                Toggle::make('is_active')
                    ->label('চালু')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('end_time')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('পণ্যসমূহ')
                    ->counts('items')
                    ->badge(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFlashSales::route('/'),
            'create' => CreateFlashSale::route('/create'),
            'edit' => EditFlashSale::route('/{record}/edit'),
        ];
    }
}
