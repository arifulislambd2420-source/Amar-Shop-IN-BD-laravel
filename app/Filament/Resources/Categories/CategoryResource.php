<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Support\ImageUpload;
use App\Models\Category;
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
use Illuminate\Support\Str;
use UnitEnum;

class CategoryResource extends Resource
{
    use HasAdminArea;

    protected static ?string $model = Category::class;

    protected static ?string $modelLabel = 'ক্যাটাগরি';

    protected static ?string $pluralModelLabel = 'ক্যাটাগরি';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'ক্যাটালগ';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, callable $set) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('icon')
                    ->label('আইকন (ইমোজি)')
                    ->maxLength(2048)
                    ->helperText('একটি ইমোজি, অথবা নিচে ছবি আপলোড করুন (ছবি দিলে সেটাই ব্যবহার হবে)।'),
                ImageUpload::make('icon_image')
                    ->label('আইকনের ছবি')
                    ->afterStateHydrated(function ($component, $state, $record): void {
                        // Show an already-saved image icon here (emoji stays in the text box).
                        if (blank($state) && $record && self::isImageIcon($record->icon)) {
                            $component->state($record->icon);
                        }
                    }),
            ]);
    }

    /** An icon value that is an image (uploaded path or URL) rather than an emoji. */
    public static function isImageIcon(?string $icon): bool
    {
        return is_string($icon) && (str_starts_with($icon, '/') || preg_match('#^https?://#i', $icon) === 1);
    }

    /** Create/Edit: an uploaded icon image wins over the text icon; the helper field is not a column. */
    public static function applyIconImage(array $data): array
    {
        if (filled($data['icon_image'] ?? null)) {
            $data['icon'] = $data['icon_image'];
        } elseif (self::isImageIcon($data['icon'] ?? null) && array_key_exists('icon_image', $data)) {
            // The image was removed in the form: drop it.
            $data['icon'] = null;
        }

        unset($data['icon_image']);

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('icon')
                    ->label('আইকন')
                    ->limit(30),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->visibleFrom('md')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('products_count')
                    ->label('পণ্য')
                    ->counts('products')
                    ->badge(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()->mutateFormDataUsing(fn (array $data): array => self::applyIconImage($data)),
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
            'index' => ManageCategories::route('/'),
        ];
    }
}
