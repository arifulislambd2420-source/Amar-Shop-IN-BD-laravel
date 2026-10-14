<?php

namespace App\Filament\Resources\Users;

use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Customers (old `users` table). Read-only-ish: no create, and the password
 * hash is never exposed in the form or table.
 */
class UserResource extends Resource
{
    use HasAdminArea;

    protected static ?string $model = User::class;

    public static function getModelLabel(): string
    {

        return Str::singular(__('গ্রাহক'));

    }

    public static function getPluralModelLabel(): string
    {

        return __('গ্রাহক');

    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function getNavigationGroup(): string|UnitEnum|null
    {

        return __('গ্রাহক');

    }

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        // No password field — hashes are never exposed.
        return $schema
            ->components([
                TextInput::make('name')
                    ->disabled(),
                TextInput::make('phone')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
