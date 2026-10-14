<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Models\Product;
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

        return __('অর্ডারের পণ্য');

    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label(__('পণ্য'))
                    ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        $product = Product::find($state);
                        if ($product) {
                            $set('product_name', $product->name);
                            $set('unit_price', $product->sale_price ?? $product->price);
                        }
                    }),
                TextInput::make('product_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('unit_price')
                    ->numeric()
                    ->prefix(__('৳'))
                    ->required()
                    ->minValue(0),
                TextInput::make('quantity')
                    ->numeric()
                    ->default(1)
                    ->required()
                    ->minValue(1),
                TextInput::make('discount')
                    ->numeric()
                    ->prefix(__('৳'))
                    ->default(0),
                TextInput::make('line_total')
                    ->label(__('লাইন মোট'))
                    ->numeric()
                    ->prefix(__('৳'))
                    ->required()
                    ->helperText(__('পরিমাণ × একক দাম − ছাড়')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_name')
            ->columns([
                TextColumn::make('product_name')
                    ->label(__('পণ্য'))
                    ->searchable(),
                TextColumn::make('unit_price')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('discount')
                    ->money('BDT'),
                TextColumn::make('line_total')
                    ->label(__('লাইন মোট'))
                    ->money('BDT')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
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
}
