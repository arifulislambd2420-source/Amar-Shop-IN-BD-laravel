<?php

namespace App\Filament\Resources\IpBlocks;

use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\IpBlocks\Pages\ManageIpBlocks;
use App\Models\IpBlock;
use App\Support\AutoBlock;
use App\Support\AdminAccess;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class IpBlockResource extends Resource
{
    use HasAdminArea;

    protected static string $adminArea = AdminAccess::AREA_SETTINGS;

    protected static ?string $model = IpBlock::class;

    public static function getModelLabel(): string
    {

        return Str::singular(__('আইপি ব্লক'));

    }

    public static function getPluralModelLabel(): string
    {

        return __('আইপি ব্লক');

    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    public static function getNavigationGroup(): string|UnitEnum|null
    {

        return __('সেটিংস');

    }

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {

        return __('আইপি ব্লক');

    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ip')
                    ->label(__('আইপি ঠিকানা'))
                    ->required()
                    ->ip()
                    ->maxLength(45)
                    ->unique(ignoreRecord: true)
                    ->placeholder('203.0.113.5')
                    // An admin must never lock themselves out of the shop by mistake.
                    ->rule(fn () => function (string $attribute, $value, \Closure $fail): void {
                        if ($value === request()->ip() || AutoBlock::isAdminIp((string) $value)) {
                            $fail(__('এটি আপনার (বা অন্য কোনো অ্যাডমিনের) নিজের আইপি — এটি ব্লক করা যাবে না।'));
                        }
                    }),
                TextInput::make('reason')
                    ->maxLength(255)
                    ->placeholder(__('কারণ (ঐচ্ছিক)')),
                DateTimePicker::make('expires_at')
                    ->label(__('ব্লকের শেষ সময় (খালি = যতদিন না সরানো হয়)')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ip')
                    ->label(__('আইপি ঠিকানা'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reason')
                    ->searchable(),
                TextColumn::make('is_auto')
                    ->label(__('ধরন'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? __('স্বয়ংক্রিয়') : __('হাতে দেওয়া'))
                    ->color(fn ($state): string => $state ? 'warning' : 'gray'),
                TextColumn::make('status')
                    ->label(__('অবস্থা'))
                    ->state(fn (IpBlock $record): string => $record->isActive() ? __('ব্লক চলছে') : __('মেয়াদ শেষ'))
                    ->badge()
                    ->color(fn (string $state): string => $state === __('ব্লক চলছে') ? 'danger' : 'gray'),
                TextColumn::make('expires_at')
                    ->label(__('শেষ হবে'))
                    ->dateTime()
                    ->placeholder(__('স্থায়ী'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_auto')
                    ->label(__('ধরন'))
                    ->placeholder(__('সব'))
                    ->trueLabel(__('স্বয়ংক্রিয়'))
                    ->falseLabel(__('হাতে দেওয়া')),
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

    public static function getPages(): array
    {
        return [
            'index' => ManageIpBlocks::route('/'),
        ];
    }
}
