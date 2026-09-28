<?php

namespace App\Filament\Resources\LandingPages;

use App\Filament\Resources\LandingPages\Pages\CreateLandingPage;
use App\Filament\Resources\LandingPages\Pages\EditLandingPage;
use App\Filament\Resources\LandingPages\Pages\ListLandingPages;
use App\Filament\Support\CloudinaryUpload;
use App\Models\LandingPage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class LandingPageResource extends Resource
{
    protected static ?string $model = LandingPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Landing Pages';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Page')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
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
                            ->unique(ignoreRecord: true)
                            ->helperText('Public URL: /lp/{slug}'),
                        Select::make('template')
                            ->options(LandingPage::TEMPLATE_OPTIONS)
                            ->default('template-1')
                            ->required(),
                        Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('The order form on this page sells this product.'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active pages are reachable at /lp/{slug}.'),
                    ]),
                Section::make('Hero')
                    ->columns(2)
                    ->schema([
                        TextInput::make('headline')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('sub_headline')
                            ->label('Sub-headline')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        CloudinaryUpload::make('hero_image')
                            ->label('Hero image')
                            ->columnSpanFull(),
                        CloudinaryUpload::make('gallery')
                            ->label('Gallery images')
                            ->multiple()
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
                Section::make('Features')
                    ->schema([
                        Repeater::make('features')
                            ->label('')
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('description')
                                    ->maxLength(500),
                            ])
                            ->columns(2)
                            ->reorderable()
                            ->addActionLabel('Add feature')
                            ->defaultItems(0),
                    ]),
                Section::make('Order form')
                    ->columns(2)
                    ->schema([
                        TextInput::make('price_override')
                            ->label('Price override')
                            ->numeric()
                            ->prefix('৳')
                            ->minValue(0)
                            ->helperText('Leave blank to use the product\'s normal price.'),
                        TextInput::make('button_text')
                            ->label('Order button text')
                            ->required()
                            ->maxLength(255)
                            ->default('এখনই অর্ডার করুন'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('slug')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('template')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => LandingPage::TEMPLATE_OPTIONS[$state] ?? (string) $state)
                    ->toggleable(),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('views')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('template')
                    ->options(LandingPage::TEMPLATE_OPTIONS),
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (LandingPage $record): string => route('landing.show', $record->slug))
                    ->openUrlInNewTab(),
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
            'index' => ListLandingPages::route('/'),
            'create' => CreateLandingPage::route('/create'),
            'edit' => EditLandingPage::route('/{record}/edit'),
        ];
    }
}
