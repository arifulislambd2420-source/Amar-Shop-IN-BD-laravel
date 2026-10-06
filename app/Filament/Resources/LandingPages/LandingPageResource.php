<?php

namespace App\Filament\Resources\LandingPages;

use App\Filament\Resources\LandingPages\Pages\CreateLandingPage;
use App\Filament\Resources\LandingPages\Pages\EditLandingPage;
use App\Filament\Resources\LandingPages\Pages\ListLandingPages;
use App\Filament\Support\CloudinaryUpload;
use App\Models\LandingPage;
use BackedEnum;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                            ->default('green')
                            ->required()
                            ->live()
                            ->helperText('Green / Purple / Cream = ব্লক-বিল্ডার পেজ। Template 1–3 (পুরনো) আগের মতো নির্দিষ্ট ফিল্ড দিয়ে চলে।'),
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
                Section::make('Hero (পুরনো টেমপ্লেট)')
                    ->visible(fn (Get $get): bool => LandingPage::isLegacyTemplate($get('template')))
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
                Section::make('Features (পুরনো টেমপ্লেট)')
                    ->visible(fn (Get $get): bool => LandingPage::isLegacyTemplate($get('template')))
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
                Section::make('Blocks (ড্র্যাগ করে ক্রম বদলান)')
                    ->visible(fn (Get $get): bool => ! LandingPage::isLegacyTemplate($get('template')))
                    ->schema([
                        Builder::make('blocks')
                            ->label('')
                            ->blocks(self::blockSchemas())
                            ->reorderable()
                            ->reorderableWithDragAndDrop()
                            ->cloneable()
                            ->collapsible()
                            ->blockPickerColumns(['default' => 2, 'md' => 3])
                            ->addActionLabel('ব্লক যোগ করুন'),
                    ]),
                Section::make('Packages (প্যাকেজ)')
                    ->description('অর্ডার ফর্মে দেখানো প্যাকেজ — যেমন ১ পিস, ২ পিস, ৩ পিস অফার।')
                    ->visible(fn (Get $get): bool => ! LandingPage::isLegacyTemplate($get('template')))
                    ->schema([
                        Repeater::make('packages')
                            ->label('')
                            ->schema([
                                Select::make('product_id')
                                    ->label('প্রোডাক্ট')
                                    ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->required(),
                                TextInput::make('label')
                                    ->label('লেবেল')
                                    ->placeholder('৩ পিস অফার')
                                    ->required()
                                    ->maxLength(255),
                                CloudinaryUpload::make('image')
                                    ->label('ছবি'),
                                TextInput::make('price')
                                    ->label('দাম')
                                    ->numeric()->minValue(0)->prefix('৳')
                                    ->required(),
                                TextInput::make('compare_price')
                                    ->label('আগের দাম')
                                    ->numeric()->minValue(0)->prefix('৳'),
                                TextInput::make('quantity')
                                    ->label('পিস/পরিমাণ')
                                    ->numeric()->minValue(1)->default(1)
                                    ->helperText('এই প্যাকেজে প্রোডাক্টের কয় ইউনিট।'),
                            ])
                            ->columns(2)
                            ->reorderable()
                            ->cloneable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->addActionLabel('প্যাকেজ যোগ করুন')
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

    /** Every block carries a "লুকাও" toggle — hidden blocks stay saved but are skipped when rendering. */
    private static function hideToggle(): Toggle
    {
        return Toggle::make('hidden')->label('লুকাও')->default(false)->inline(false);
    }

    private static function block(string $type, string $label, string $icon, array $fields): Block
    {
        return Block::make($type)
            ->label(fn (?array $state): string => $label.(($state['hidden'] ?? false) ? ' — লুকানো' : ''))
            ->icon($icon)
            ->columns(2)
            ->schema([...$fields, self::hideToggle()->columnSpanFull()]);
    }

    /** @return array<int, Block> */
    public static function blockSchemas(): array
    {
        return [
            self::block('hero', 'Hero', 'heroicon-o-star', [
                TextInput::make('headline')->label('শিরোনাম')->required()->maxLength(255)->columnSpanFull(),
                TextInput::make('offer_line')->label('দাম/অফার লাইন')->placeholder('মূল্যঃ ১ পিস ৪৮০/- টাকা, ৩ পিস ১১৫০/- টাকা')->maxLength(255)->columnSpanFull(),
                TextInput::make('button_text')->label('বাটনের লেখা')->default('অর্ডার করুন')->maxLength(100),
                TextInput::make('badge_text')->label('ব্যাজ/ঘোষণা')->placeholder('নতুন বছর উপলক্ষে বিশেষ মূল্য ছাড়')->maxLength(255),
                CloudinaryUpload::make('image')->label('ছবি')->columnSpanFull(),
                TextInput::make('video_url')->label('ভিডিও লিংক (ঐচ্ছিক)')->url()->maxLength(500)->columnSpanFull(),
                TextInput::make('list_heading')->label('বৈশিষ্ট্য তালিকার শিরোনাম')->default('পণ্যের বৈশিষ্ট্যসমূহ')->maxLength(255)->columnSpanFull(),
                Repeater::make('bullets')->label('বৈশিষ্ট্য তালিকা')
                    ->simple(TextInput::make('text')->required()->maxLength(255))
                    ->reorderable()->addActionLabel('লাইন যোগ করুন')->defaultItems(0)->columnSpanFull(),
            ]),
            self::block('features', 'Features', 'heroicon-o-sparkles', [
                TextInput::make('heading')->label('শিরোনাম')->maxLength(255)->columnSpanFull(),
                Repeater::make('items')->label('ফিচার')
                    ->schema([
                        TextInput::make('title')->label('শিরোনাম')->required()->maxLength(255),
                        TextInput::make('description')->label('বিবরণ')->maxLength(500),
                        CloudinaryUpload::make('icon')->label('আইকন/ছবি'),
                    ])->columns(2)->reorderable()->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->addActionLabel('ফিচার যোগ করুন')->defaultItems(0)->columnSpanFull(),
            ]),
            self::block('checklist', 'Checklist', 'heroicon-o-check-circle', [
                TextInput::make('heading')->label('শিরোনাম')->placeholder('ফ্রন্ট বাটন ব্রা ব্যবহারে যেসব সুবিধা পাবেন:')->maxLength(255)->columnSpanFull(),
                Textarea::make('intro')->label('ভূমিকা')->rows(3)->columnSpanFull(),
                Repeater::make('items')->label('তালিকা')
                    ->simple(TextInput::make('text')->required()->maxLength(255))
                    ->reorderable()->addActionLabel('লাইন যোগ করুন')->defaultItems(0)->columnSpanFull(),
                CloudinaryUpload::make('image')->label('পাশের ছবি'),
                TextInput::make('button_text')->label('বাটনের লেখা (খালি = বাটন নেই)')->maxLength(100),
            ]),
            self::block('gallery', 'Gallery', 'heroicon-o-photo', [
                TextInput::make('heading')->label('শিরোনাম')->maxLength(255)->columnSpanFull(),
                CloudinaryUpload::make('images')->label('ছবি')->multiple()->reorderable()->columnSpanFull(),
                Select::make('layout')->label('লেআউট')->options(['carousel' => 'Carousel', 'grid' => 'Grid'])->default('carousel'),
            ]),
            self::block('reviews', 'Reviews', 'heroicon-o-chat-bubble-left-right', [
                TextInput::make('heading')->label('শিরোনাম')->default('সম্মানিত কাস্টমার রিভিউ')->maxLength(255)->columnSpanFull(),
                CloudinaryUpload::make('images')->label('রিভিউ স্ক্রিনশট')->multiple()->reorderable()->columnSpanFull(),
                TextInput::make('button_text')->label('বাটনের লেখা (খালি = বাটন নেই)')->maxLength(100),
            ]),
            self::block('video', 'Video', 'heroicon-o-play-circle', [
                TextInput::make('heading')->label('শিরোনাম')->maxLength(255)->columnSpanFull(),
                TextInput::make('url')->label('ভিডিও লিংক (YouTube / MP4)')->url()->required()->maxLength(500)->columnSpanFull(),
                CloudinaryUpload::make('poster')->label('পোস্টার ছবি'),
            ]),
            self::block('variants', 'Variants (সাইজ/কালার)', 'heroicon-o-swatch', [
                TextInput::make('heading')->label('শিরোনাম')->maxLength(255)->columnSpanFull(),
                TextInput::make('size_label')->label('সাইজের লেবেল')->default('সাইজ নির্বাচন করুন')->maxLength(100),
                TagsInput::make('sizes')->label('সাইজ'),
                Toggle::make('size_required')->label('সাইজ আবশ্যক')->default(true),
                TextInput::make('color_label')->label('কালারের লেবেল')->default('কালার নির্বাচন করুন')->maxLength(100),
                TagsInput::make('colors')->label('কালার'),
                Toggle::make('color_required')->label('কালার আবশ্যক')->default(false),
            ]),
            self::block('cta', 'CTA / কল', 'heroicon-o-phone', [
                TextInput::make('heading')->label('লেখা')->placeholder('বিশেষ প্রয়োজনে কল করুনঃ')->maxLength(255)->columnSpanFull(),
                TextInput::make('phone')->label('ফোন নম্বর (ঐচ্ছিক)')->tel()->maxLength(30),
                TextInput::make('button_text')->label('বাটনের লেখা (খালি = বাটন নেই)')->default('অর্ডার করুন')->maxLength(100),
            ]),
            self::block('notice', 'Notice (গুরুত্বপূর্ণ বিষয়)', 'heroicon-o-exclamation-circle', [
                TextInput::make('heading')->label('শিরোনাম')->placeholder('২ টি গুরুত্বপূর্ণ বিষয়ঃ')->maxLength(255)->columnSpanFull(),
                Repeater::make('items')->label('পয়েন্ট')
                    ->simple(TextInput::make('text')->required()->maxLength(500))
                    ->reorderable()->addActionLabel('পয়েন্ট যোগ করুন')->defaultItems(0)->columnSpanFull(),
            ]),
            self::block('order_form', 'Order form', 'heroicon-o-shopping-bag', [
                TextInput::make('heading')->label('শিরোনাম')->placeholder('সঠিক তথ্য দিয়ে নিচের ফর্মটি পূরণ করুন')->maxLength(255)->columnSpanFull(),
                TextInput::make('button_text')->label('অর্ডার বাটনের লেখা')->default('অর্ডার কনফার্ম করুন')->maxLength(100),
                Textarea::make('note')->label('ফর্মের নিচের নোট')->rows(2)->columnSpanFull(),
            ]),
        ];
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
                self::duplicateAction(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label('Duplicate')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('এই পেজের একটি কপি (inactive অবস্থায়) তৈরি হবে। ভিউ ও অর্ডারের সংখ্যা কপি হয় না।')
            ->action(function (LandingPage $record) {
                $copy = $record->duplicate();

                Notification::make()->title('Duplicated — ইনঅ্যাকটিভ কপি তৈরি হয়েছে।')->success()->send();

                return redirect(static::getUrl('edit', ['record' => $copy]));
            });
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
