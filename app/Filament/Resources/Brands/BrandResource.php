<?php

namespace App\Filament\Resources\Brands;

use App\Filament\Support\RowMenu;
use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\Brands\Pages\ManageBrands;
use App\Filament\Support\ImageUpload;
use App\Models\Brand;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class BrandResource extends Resource
{
    use HasAdminArea;

    protected static ?string $model = Brand::class;

    public static function getModelLabel(): string
    {

        return Str::singular(__('ব্র্যান্ড'));

    }

    public static function getPluralModelLabel(): string
    {

        return __('ব্র্যান্ড');

    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function getNavigationGroup(): string|UnitEnum|null
    {

        return __('ক্যাটালগ');

    }

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                ImageUpload::make('logo')
                    ->label(__('লোগো')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->label(__('লোগো'))
                    ->circular(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('products_count')
                    ->label(__('পণ্য'))
                    ->counts('products')
                    ->badge(),
            ])
            ->defaultSort('name')
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
            'index' => ManageBrands::route('/'),
        ];
    }
}
