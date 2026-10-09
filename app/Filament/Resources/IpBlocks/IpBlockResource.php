<?php

namespace App\Filament\Resources\IpBlocks;

use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\IpBlocks\Pages\ManageIpBlocks;
use App\Models\IpBlock;
use App\Support\AdminAccess;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class IpBlockResource extends Resource
{
    use HasAdminArea;

    protected static string $adminArea = AdminAccess::AREA_SETTINGS;

    protected static ?string $model = IpBlock::class;

    protected static ?string $modelLabel = 'আইপি ব্লক';

    protected static ?string $pluralModelLabel = 'আইপি ব্লক';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'আইপি ব্লক';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ip')
                    ->label('আইপি ঠিকানা')
                    ->required()
                    ->maxLength(45)
                    ->unique(ignoreRecord: true)
                    ->placeholder('203.0.113.5'),
                TextInput::make('reason')
                    ->maxLength(255)
                    ->placeholder('কারণ (ঐচ্ছিক)'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ip')
                    ->label('আইপি ঠিকানা')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reason')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
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
            'index' => ManageIpBlocks::route('/'),
        ];
    }
}
