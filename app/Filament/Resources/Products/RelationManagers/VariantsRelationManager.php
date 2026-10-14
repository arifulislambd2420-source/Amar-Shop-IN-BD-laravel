<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Support\ImageUpload;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {

        return __('রং / সাইজ / ভ্যারিয়েন্ট');

    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('color')
                    ->label(__('রং'))
                    ->placeholder(__('যেমন: লাল'))
                    ->maxLength(60),
                ColorPicker::make('color_hex')
                    ->label(__('রঙের নমুনা (ঐচ্ছিক)')),
                TextInput::make('size')
                    ->label(__('সাইজ'))
                    ->placeholder(__('যেমন: M'))
                    ->maxLength(60),
                TextInput::make('label')
                    ->label(__('নাম (রং/সাইজ না দিলে)'))
                    ->helperText(__('রং বা সাইজ দিলে নাম নিজে থেকেই "রং / সাইজ" হয়ে যাবে।'))
                    ->required(fn (Get $get) => blank($get('color')) && blank($get('size')))
                    ->maxLength(255),
                ImageUpload::make('image')
                    ->label(__('এই রঙের ছবি'))
                    ->helperText(__('রং বাছলে প্রোডাক্ট পেজে এই ছবিটি দেখাবে।'))
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->numeric()
                    ->prefix(__('৳'))
                    ->required()
                    ->minValue(0),
                TextInput::make('stock')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                ImageColumn::make('image')->label(__('ছবি'))->square()->size(40),
                TextColumn::make('label')
                    ->searchable(),
                TextColumn::make('price')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('stock')
                    ->numeric()
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
