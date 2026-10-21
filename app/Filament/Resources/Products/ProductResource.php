<?php

namespace App\Filament\Resources\Products;

use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Support\ImageUpload;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\Action;
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
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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

    public static function getModelLabel(): string
    {

        return Str::singular(__('পণ্য'));

    }

    public static function getPluralModelLabel(): string
    {

        return __('পণ্য');

    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    public static function getNavigationGroup(): string|UnitEnum|null
    {

        return __('ক্যাটালগ');

    }

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'name';

    public const STATUS_OPTIONS = [
        'draft' => 'খসড়া',
        'published' => 'প্রকাশিত',
        'hidden' => 'লুকানো',
        'outofstock' => 'স্টক শেষ',
        'archived' => 'আর্কাইভ',
    ];

    public static function statuses(): array
    {
        return array_map('__', self::STATUS_OPTIONS);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('বিবরণ'))
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
                            ->label(__('এসকেইউ (SKU)'))
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('status')
                            ->options(self::statuses())
                            ->default('published')
                            ->required(),
                        ImageUpload::configureRichEditor(RichEditor::make('description'))
                            ->label(__('বিস্তারিত বিবরণ'))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('দাম ও স্টক'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('price')
                            ->numeric()
                            ->prefix(__('৳'))
                            ->required()
                            ->minValue(0),
                        TextInput::make('sale_price')
                            ->numeric()
                            ->prefix(__('৳'))
                            ->minValue(0),
                        TextInput::make('cost_price')
                            ->numeric()
                            ->prefix(__('৳'))
                            ->minValue(0),
                        TextInput::make('stock')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Toggle::make('is_active')
                            ->label(__('চালু'))
                            ->default(true),
                    ]),
                Section::make(__('শ্রেণিবিন্যাস'))
                    ->columns(2)
                    ->schema([
                        Select::make('category_id')
                            ->label(__('ক্যাটাগরি'))
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('brand_id')
                            ->label(__('ব্র্যান্ড'))
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload(),
                        ImageUpload::make('image')
                            ->label(__('মূল ছবি'))
                            ->columnSpanFull(),
                        TextInput::make('tags')
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText(__('কমা দিয়ে ট্যাগ লিখুন।')),
                    ]),
                Section::make(__('ছবির গ্যালারি'))
                    ->description(__('যত খুশি ছবি দিন — কম্পিউটার থেকে, লাইব্রেরি থেকে বা লিংক দিয়ে, একসাথে একাধিক। টেনে ক্রম বদলান; প্রথম ছবিটি আগে দেখাবে। রঙের ছবি আলাদা (রং/ভ্যারিয়েন্ট ট্যাবে) এবং রং বাছলে নিজে থেকেই দেখাবে।'))
                    ->schema([
                        ImageUpload::make('gallery')
                            ->hiddenLabel()
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->panelLayout('grid')
                            ->dehydrated(false)
                            ->loadStateFromRelationshipsUsing(function (FileUpload $component, ?Product $record): void {
                                $component->state($record?->galleryUrls() ?? []);
                            })
                            ->saveRelationshipsUsing(function (Product $record, ?array $state): void {
                                $record->syncGallery(array_values($state ?? []));
                            })
                            ->columnSpanFull(),
                    ]),
                Section::make(__('এসইও'))
                    ->columns(1)
                    ->collapsed()
                    ->schema([
                        TextInput::make('seo_title')
                            ->label(__('এসইও টাইটেল'))
                            ->maxLength(255),
                        Textarea::make('meta_description')
                            ->label(__('মেটা বিবরণ'))
                            ->rows(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label(__('ছবি')),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('sku')
                    ->visibleFrom('lg')
                    ->label(__('এসকেইউ (SKU)'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('category.name')
                    ->visibleFrom('lg')
                    ->label(__('ক্যাটাগরি'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('brand.name')
                    ->visibleFrom('lg')
                    ->label(__('ব্র্যান্ড'))
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
                    ->formatStateUsing(fn (?string $state): string => self::statuses()[$state] ?? (string) $state)
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('category_id')
                    ->label(__('ক্যাটাগরি'))
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('brand_id')
                    ->label(__('ব্র্যান্ড'))
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->options(self::statuses()),
                TernaryFilter::make('stock')
                    ->label(__('স্টক'))
                    ->placeholder(__('সব'))
                    ->trueLabel(__('স্টকে আছে'))
                    ->falseLabel(__('স্টক শেষ'))
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
                        ->label(__('কপি করুন'))
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

    /**
     * Change a product's stock from a list (dashboard low-stock widget). A product with sizes / colours keeps its
     * stock in the variants, so for those the button opens the product instead.
     */
    public static function quickStockAction(): Action
    {
        return Action::make('quickStock')
            ->label(__('স্টক আপডেট'))
            ->icon(Heroicon::OutlinedPlusCircle)
            ->iconButton()
            ->tooltip(__('স্টক আপডেট'))
            ->color('warning')
            ->modalHeading(fn (Product $record): string => __(':name — স্টক আপডেট', ['name' => $record->name]))
            ->modalSubmitActionLabel(__('সেভ করুন'))
            ->fillForm(fn (Product $record): array => ['stock' => $record->stock])
            ->schema([
                TextInput::make('stock')
                    ->label(__('এখন স্টকে কত পিস'))
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(1000000)
                    ->required(),
            ])
            ->visible(fn (Product $record): bool => ! $record->variants()->exists())
            ->action(function (Product $record, array $data): void {
                $record->update(['stock' => (int) $data['stock']]);

                Notification::make()->title(__(':name — স্টক :stock পিস করা হয়েছে।', ['name' => $record->name, 'stock' => (int) $data['stock']]))->success()->send();
            });
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
