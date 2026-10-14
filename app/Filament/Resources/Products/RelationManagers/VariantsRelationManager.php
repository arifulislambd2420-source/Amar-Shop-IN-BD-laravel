<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use App\Filament\Support\ImageUpload;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\ImageColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'রং / সাইজ / ভ্যারিয়েন্ট';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('color')
                    ->label('রং')
                    ->placeholder('যেমন: লাল')
                    ->maxLength(60),
                ColorPicker::make('color_hex')
                    ->label('রঙের নমুনা (ঐচ্ছিক)'),
                TextInput::make('size')
                    ->label('সাইজ')
                    ->placeholder('যেমন: M')
                    ->maxLength(60),
                TextInput::make('label')
                    ->label('নাম (রং/সাইজ না দিলে)')
                    ->helperText('রং বা সাইজ দিলে নাম নিজে থেকেই "রং / সাইজ" হয়ে যাবে।')
                    ->required(fn (Get $get) => blank($get('color')) && blank($get('size')))
                    ->maxLength(255),
                ImageUpload::make('image')
                    ->label('এই রঙের ছবি')
                    ->helperText('রং বাছলে প্রোডাক্ট পেজে এই ছবিটি দেখাবে।')
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->numeric()
                    ->prefix('৳')
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
                ImageColumn::make('image')->label('ছবি')->square()->size(40),
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
