<?php

namespace App\Filament\Resources\FlashSales\RelationManagers;

use App\Filament\Support\RowMenu;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {

        return __('ফ্ল্যাশ সেলের পণ্য');

    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label(__('পণ্য'))
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('flash_price')
                    ->label(__('ফ্ল্যাশ দাম'))
                    ->numeric()
                    ->prefix(__('৳'))
                    ->required()
                    ->minValue(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('flash_price')
            ->columns([
                TextColumn::make('product.name')
                    ->label(__('পণ্য'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('flash_price')
                    ->money('BDT')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
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
}
