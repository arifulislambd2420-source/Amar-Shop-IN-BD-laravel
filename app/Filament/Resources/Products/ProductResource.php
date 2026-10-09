<?php

namespace App\Filament\Resources\Products;

use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\ImagesRelationManager;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Support\ImageUpload;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use UnitEnum;

class ProductResource extends Resource
{
    use HasAdminArea;

    protected static ?string $model = Product::class;

    protected static ?string $modelLabel = 'পণ্য';

    protected static ?string $pluralModelLabel = 'পণ্য';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'ক্যাটালগ';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'name';

    public const STATUS_OPTIONS = [
        'draft' => 'খসড়া',
        'published' => 'প্রকাশিত',
        'hidden' => 'লুকানো',
        'outofstock' => 'স্টক শেষ',
        'archived' => 'আর্কাইভ',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('বিবরণ')
                    ->columns(2)
                    ->schema([
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
                        TextInput::make('sku')
                            ->label('এসকেইউ (SKU)')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('status')
                            ->options(self::STATUS_OPTIONS)
                            ->default('published')
                            ->required(),
                        ImageUpload::configureRichEditor(RichEditor::make('description'))
                            ->label('বিস্তারিত বিবরণ')
                            ->columnSpanFull(),
                    ]),
                Section::make('দাম ও স্টক')
                    ->columns(2)
                    ->schema([
                        TextInput::make('price')
                            ->numeric()
                            ->prefix('৳')
                            ->required()
                            ->minValue(0),
                        TextInput::make('sale_price')
                            ->numeric()
                            ->prefix('৳')
                            ->minValue(0),
                        TextInput::make('cost_price')
                            ->numeric()
                            ->prefix('৳')
                            ->minValue(0),
                        TextInput::make('stock')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Toggle::make('is_active')
                            ->label('চালু')
                            ->default(true),
                    ]),
                Section::make('শ্রেণিবিন্যাস')
                    ->columns(2)
                    ->schema([
                        Select::make('category_id')
                            ->label('ক্যাটাগরি')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('brand_id')
                            ->label('ব্র্যান্ড')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload(),
                        ImageUpload::make('image')
                            ->label('মূল ছবি')
                            ->columnSpanFull(),
                        TextInput::make('tags')
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('কমা দিয়ে ট্যাগ লিখুন।'),
                    ]),
                Section::make('এসইও')
                    ->columns(1)
                    ->collapsed()
                    ->schema([
                        TextInput::make('seo_title')
                            ->label('এসইও টাইটেল')
                            ->maxLength(255),
                        Textarea::make('meta_description')
                            ->label('মেটা বিবরণ')
                            ->rows(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('ছবি'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('sku')
                    ->visibleFrom('lg')
                    ->label('এসকেইউ (SKU)')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('category.name')
                    ->visibleFrom('lg')
                    ->label('ক্যাটাগরি')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('brand.name')
                    ->visibleFrom('lg')
                    ->label('ব্র্যান্ড')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('price')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('stock')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::STATUS_OPTIONS[$state] ?? (string) $state)
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('category_id')
                    ->label('ক্যাটাগরি')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('brand_id')
                    ->label('ব্র্যান্ড')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->options(self::STATUS_OPTIONS),
                TernaryFilter::make('stock')
                    ->label('স্টক')
                    ->placeholder('সব')
                    ->trueLabel('স্টকে আছে')
                    ->falseLabel('স্টক শেষ')
                    ->queries(
                        true: fn (Builder $query) => $query->where('stock', '>', 0),
                        false: fn (Builder $query) => $query->where('stock', '<=', 0),
                        blank: fn (Builder $query) => $query,
                    ),
                TrashedFilter::make(),
            ])
            // Edit as an icon, the rest in a ⋯ menu — keeps the table narrow on phones.
            ->recordActions([
                EditAction::make()->iconButton(),
                ActionGroup::make([
                    ReplicateAction::make()
                        ->label('কপি করুন')
                        ->excludeAttributes(['slug', 'sku'])
                        ->beforeReplicaSaved(function (Product $replica): void {
                            $replica->name = $replica->name.' (copy)';
                            $replica->slug = Str::slug($replica->name).'-'.Str::random(5);
                            $replica->sku = null;
                        }),
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRelations(): array
    {
        return [
            VariantsRelationManager::class,
            ImagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
