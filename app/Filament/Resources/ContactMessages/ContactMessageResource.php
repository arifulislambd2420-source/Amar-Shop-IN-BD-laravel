<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Support\RowMenu;
use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\ContactMessages\Pages\ManageContactMessages;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Contact form submissions — read-only list (view + delete only).
 */
class ContactMessageResource extends Resource
{
    use HasAdminArea;

    protected static ?string $model = ContactMessage::class;

    public static function getModelLabel(): string
    {

        return Str::singular(__('যোগাযোগের বার্তা'));

    }

    public static function getPluralModelLabel(): string
    {

        return __('যোগাযোগের বার্তা');

    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    public static function getNavigationGroup(): string|UnitEnum|null
    {

        return __('গ্রাহক');

    }

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->disabled(),
                TextInput::make('phone')->disabled(),
                TextInput::make('email')->disabled(),
                TextInput::make('subject')->disabled(),
                Textarea::make('message')->rows(6)->columnSpanFull()->disabled(),
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
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('subject')
                    ->toggleable()
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('message')
                    ->limit(50)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make()->iconButton(),
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
            'index' => ManageContactMessages::route('/'),
        ];
    }
}
