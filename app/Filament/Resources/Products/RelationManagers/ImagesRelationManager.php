<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Support\ImageUpload;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {

        return __('ছবি');

    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageUpload::make('url')
                    ->label(__('ছবি'))
                    ->required(),
                TextInput::make('alt')
                    ->label(__('ছবির বিবরণ (Alt)'))
                    ->maxLength(255),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('url')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('url')
                    ->label(__('ছবি')),
                TextColumn::make('alt')
                    ->label(__('বিবরণ'))
                    ->limit(40),
                TextColumn::make('sort_order')
                    ->label(__('ক্রম'))
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
