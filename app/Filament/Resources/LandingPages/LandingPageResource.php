<?php

namespace App\Filament\Resources\LandingPages;

use App\Filament\Support\RowMenu;
use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\LandingPages\Pages\CreateLandingPage;
use App\Filament\Resources\LandingPages\Pages\EditLandingPage;
use App\Filament\Resources\LandingPages\Pages\ListLandingPages;
use App\Filament\Support\ImageUpload;
use App\Models\LandingPage;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
    use HasAdminArea;

    protected static ?string $model = LandingPage::class;

    public static function getModelLabel(): string
    {

        return Str::singular(__('ল্যান্ডিং পেজ'));

    }

    public static function getPluralModelLabel(): string
    {

        return __('ল্যান্ডিং পেজ');

    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    public static function getNavigationGroup(): string|UnitEnum|null
    {

        return __('বিক্রয়');

    }

    public static function getNavigationLabel(): string
    {

        return __('ল্যান্ডিং পেজ');

    }

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('পেজ'))
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
                            ->helperText(__('পাবলিক লিংক: /lp/{slug}')),
                        Select::make('template')
                            ->options(array_map('__', LandingPage::TEMPLATE_OPTIONS))
                            ->default('green')
                            ->required()
                            ->live()
                            ->helperText(__('সবুজ / বেগুনি / ক্রিম = ব্লক-বিল্ডার পেজ। টেমপ্লেট ১–৩ (পুরনো) আগের মতো নির্দিষ্ট ফিল্ড দিয়ে চলে।')),
                        Select::make('product_id')
                            ->label(__('পণ্য'))
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText(__('এই পেজের অর্ডার ফর্ম থেকে এই পণ্যই বিক্রি হবে।')),
                        Toggle::make('is_active')
                            ->label(__('চালু'))
                            ->default(true)
                            ->helperText(__('শুধু চালু পেজ /lp/{slug} লিংকে খোলে।')),
                        ColorPicker::make('primary_color')
                            ->label(__('মূল রং'))
                            ->visible(fn (Get $get): bool => ! LandingPage::isLegacyTemplate($get('template')))
                            ->helperText(fn (Get $get): string => __('খালি = টেমপ্লেটের ডিফল্ট (:color)। সাইটের ব্র্যান্ড রংয়ের সাথে সম্পর্ক নেই।', ['color' => LandingPage::TEMPLATE_COLORS[$get('template')]['primary'] ?? '—']))
                            ->hintAction(fn (): Action => Action::make('resetPrimary')->label(__('ডিফল্ট'))->action(fn (Set $set) => $set('primary_color', null))),
                        ColorPicker::make('secondary_color')
                            ->label(__('দ্বিতীয় রং'))
                            ->visible(fn (Get $get): bool => ! LandingPage::isLegacyTemplate($get('template')))
                            ->helperText(fn (Get $get): string => __('খালি = টেমপ্লেটের ডিফল্ট (:color)', ['color' => LandingPage::TEMPLATE_COLORS[$get('template')]['secondary'] ?? '—']))
                            ->hintAction(fn (): Action => Action::make('resetSecondary')->label(__('ডিফল্ট'))->action(fn (Set $set) => $set('secondary_color', null))),
                    ]),
                Section::make(__('Hero (পুরনো টেমপ্লেট)'))
                    ->visible(fn (Get $get): bool => LandingPage::isLegacyTemplate($get('template')))
                    ->columns(2)
                    ->schema([
                        TextInput::make('headline')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('sub_headline')
                            ->label(__('উপশিরোনাম'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        ImageUpload::make('hero_image')
                            ->label(__('হিরো ছবি'))
                            ->columnSpanFull(),
                        ImageUpload::make('gallery')
                            ->label(__('গ্যালারির ছবি'))
                            ->multiple()
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Features (পুরনো টেমপ্লেট)'))
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
                            ->addActionLabel(__('বৈশিষ্ট্য যোগ করুন'))
                            ->defaultItems(0),
                    ]),
                Section::make(__('ব্লক (তীর দিয়ে ক্রম বদলান)'))
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
                            ->addActionLabel(__('ব্লক যোগ করুন')),
                    ]),
                Section::make(__('প্যাকেজ'))
                    ->description(__('অর্ডার ফর্মে দেখানো প্যাকেজ — যেমন ১ পিস, ২ পিস, ৩ পিস অফার।'))
                    ->visible(fn (Get $get): bool => ! LandingPage::isLegacyTemplate($get('template')))
                    ->schema([
                        Repeater::make('packages')
                            ->label('')
                            ->schema([
                                Select::make('product_id')
                                    ->label(__('প্রোডাক্ট'))
                                    ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->required(),
                                TextInput::make('label')
                                    ->label(__('লেবেল'))
                                    ->placeholder(__('৩ পিস অফার'))
                                    ->required()
                                    ->maxLength(255),
                                ImageUpload::make('image')
                                    ->label(__('ছবি')),
                                TextInput::make('price')
                                    ->label(__('দাম'))
                                    ->numeric()->minValue(0)->prefix(__('৳'))
                                    ->required(),
                                TextInput::make('compare_price')
                                    ->label(__('আগের দাম'))
                                    ->numeric()->minValue(0)->prefix(__('৳')),
                                TextInput::make('quantity')
                                    ->label(__('পিস/পরিমাণ'))
                                    ->numeric()->minValue(1)->default(1)
                                    ->helperText(__('এই প্যাকেজে প্রোডাক্টের কয় ইউনিট।')),
                            ])
                            ->columns(2)
                            ->reorderable()
                            ->cloneable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->addActionLabel(__('প্যাকেজ যোগ করুন'))
                            ->defaultItems(0),
                    ]),
                Section::make(__('এসইও'))
                    ->description(__('এই পেজের নিজস্ব সার্চ/শেয়ার তথ্য। খালি থাকলে শিরোনাম ও বিবরণ থেকে নেওয়া হয়।'))
                    ->collapsible()
                    ->schema([
                        TextInput::make('seo_title')->label(__('এসইও টাইটেল'))->maxLength(120),
                        Textarea::make('seo_description')->label(__('মেটা বিবরণ'))->rows(2)->maxLength(300),
                        ImageUpload::make('og_image')->label(__('OG ছবি (শেয়ারের ছবি)'))->helperText(__('খালি = হিরো ছবি, তারপর সাইটের ডিফল্ট OG ছবি।')),
                    ]),
                Section::make(__('অর্ডার ফর্ম'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('price_override')
                            ->label(__('আলাদা দাম'))
                            ->numeric()
                            ->prefix(__('৳'))
                            ->minValue(0)
                            ->helperText(__('খালি রাখলে পণ্যের সাধারণ দামই ধরা হবে।')),
                        TextInput::make('button_text')
                            ->label(__('অর্ডার বাটনের লেখা'))
                            ->required()
                            ->maxLength(255)
                            ->default(__('এখনই অর্ডার করুন')),
                    ]),
            ]);
    }

    /** Every block carries a "লুকাও" toggle — hidden blocks stay saved but are skipped when rendering. */
    private static function hideToggle(): Toggle
    {
        return Toggle::make('hidden')->label(__('লুকাও'))->default(false)->inline(false);
    }

    private static function block(string $type, string $label, string $icon, array $fields): Block
    {
        return Block::make($type)
            ->label(fn (?array $state): string => $label.(($state['hidden'] ?? false) ? ' — '.__('লুকানো') : ''))
            ->icon($icon)
            ->columns(2)
            ->schema([...$fields, self::hideToggle()->columnSpanFull()]);
    }

    /** A video link: YouTube, Facebook, Vimeo or a direct .mp4/.webm — anything else is refused with a clear message. */
    private static function videoLink(string $name): TextInput
    {
        return TextInput::make($name)
            ->url()
            ->maxLength(500)
            ->columnSpanFull()
            ->rule(fn () => function (string $attribute, $value, \Closure $fail): void {
                if (filled($value) && \App\Support\VideoEmbed::parse((string) $value) === null) {
                    $fail(__('এই লিংক চলবে না। YouTube বা Facebook ভিডিওর লিংক (বা সরাসরি .mp4 ফাইলের লিংক) দিন।'));
                }
            })
            ->helperText(__('YouTube বা Facebook ভিডিওর লিংক বসান। ভিডিও চাপলে তবেই লোড হয়, তাই পেজ ধীর হয় না।'));
    }

    /** @return array<int, Block> */
    public static function blockSchemas(): array
    {
        return [
            self::block('hero', __('হিরো'), 'heroicon-o-star', [
                TextInput::make('headline')->label(__('শিরোনাম'))->required()->maxLength(255)->columnSpanFull(),
                TextInput::make('highlight')->label(__('হাইলাইট লাইন (ঐচ্ছিক)'))->placeholder(__('এই সুযোগ সীমিত সময়ের জন্য এবং স্টক থাকা পর্যন্ত'))->maxLength(255)->columnSpanFull(),
                Textarea::make('description')->label(__('বর্ণনা (ঐচ্ছিক)'))->rows(3)->columnSpanFull(),
                TextInput::make('offer_line')->label(__('দাম/অফার লাইন (বা রঙিন দ্বিতীয় শিরোনাম)'))->placeholder(__('মূল্যঃ ১ পিস ৪৮০/- টাকা, ৩ পিস ১১৫০/- টাকা'))->maxLength(255)->columnSpanFull(),
                TextInput::make('button_text')->label(__('বাটনের লেখা'))->default(__('অর্ডার করুন'))->maxLength(100),
                TextInput::make('badge_text')->label(__('ব্যাজ/ঘোষণা'))->placeholder(__('নতুন বছর উপলক্ষে বিশেষ মূল্য ছাড়'))->maxLength(255),
                ImageUpload::make('images')->label(__('ছবি (একাধিক হলে স্লাইডার)'))->multiple()->reorderable()->columnSpanFull(),
                self::videoLink('video_url')->label(__('ভিডিও লিংক (ঐচ্ছিক)')),
                Select::make('hero_media')->label(__('হিরোতে কী দেখাবে'))
                    ->options(['image' => __('ছবি (ভিডিও দিলে ছবির উপর ▶ বাটন)'), 'video' => __('ভিডিও — ছবির বদলে')])
                    ->default('image')->native(false)->columnSpanFull()
                    ->helperText(__('"ভিডিও" বাছলে উপরের ভিডিও লিংকটি ছবির জায়গায় বসবে (মোবাইলে পুরো চওড়া, 16:9)। ছবি না দিয়ে শুধু ভিডিও লিংক দিলেও ভিডিওই দেখাবে।')),
                TextInput::make('list_heading')->label(__('বৈশিষ্ট্য তালিকার শিরোনাম'))->default(__('পণ্যের বৈশিষ্ট্যসমূহ'))->maxLength(255)->columnSpanFull(),
                Repeater::make('bullets')->label(__('বৈশিষ্ট্য তালিকা'))
                    ->simple(TextInput::make('text')->required()->maxLength(255))
                    ->reorderable()->addActionLabel(__('লাইন যোগ করুন'))->defaultItems(0)->columnSpanFull(),
            ]),
            self::block('features', __('বৈশিষ্ট্য'), 'heroicon-o-sparkles', [
                TextInput::make('label')->label(__('ছোট লেবেল (ঐচ্ছিক)'))->placeholder('PREMIUM COLLECTION')->maxLength(100),
                TextInput::make('heading')->label(__('শিরোনাম'))->maxLength(255),
                TextInput::make('accent')->label(__('রঙিন দ্বিতীয় লাইন (ঐচ্ছিক)'))->maxLength(255)->columnSpanFull(),
                Textarea::make('description')->label(__('বর্ণনা (ঐচ্ছিক)'))->rows(3)->columnSpanFull(),
                ImageUpload::make('images')->label(__('পাশের ছবি (ঐচ্ছিক — কোলাজ)'))->multiple()->reorderable()->columnSpanFull(),
                Repeater::make('items')->label(__('ফিচার'))
                    ->schema([
                        TextInput::make('title')->label(__('শিরোনাম'))->required()->maxLength(255),
                        TextInput::make('description')->label(__('বিবরণ'))->maxLength(500),
                        ImageUpload::make('icon')->label(__('আইকন/ছবি')),
                    ])->columns(2)->reorderable()->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->addActionLabel(__('ফিচার যোগ করুন'))->defaultItems(0)->columnSpanFull(),
                TextInput::make('button_text')->label(__('বাটনের লেখা (খালি = বাটন নেই)'))->maxLength(100),
                TextInput::make('phone')->label(__('ফোন নম্বর (ঐচ্ছিক)'))->tel()->maxLength(30),
            ]),
            self::block('checklist', __('চেকলিস্ট'), 'heroicon-o-check-circle', [
                TextInput::make('heading')->label(__('শিরোনাম'))->placeholder(__('ফ্রন্ট বাটন ব্রা ব্যবহারে যেসব সুবিধা পাবেন:'))->maxLength(255)->columnSpanFull(),
                Textarea::make('intro')->label(__('ভূমিকা'))->rows(3)->columnSpanFull(),
                Repeater::make('items')->label(__('তালিকা'))
                    ->simple(TextInput::make('text')->required()->maxLength(255))
                    ->reorderable()->addActionLabel(__('লাইন যোগ করুন'))->defaultItems(0)->columnSpanFull(),
                ImageUpload::make('image')->label(__('পাশের ছবি')),
                TextInput::make('button_text')->label(__('বাটনের লেখা (খালি = বাটন নেই)'))->maxLength(100),
            ]),
            self::block('gallery', __('গ্যালারি'), 'heroicon-o-photo', [
                TextInput::make('heading')->label(__('শিরোনাম'))->maxLength(255)->columnSpanFull(),
                ImageUpload::make('images')->label(__('ছবি'))->multiple()->reorderable()->columnSpanFull(),
                Select::make('layout')->label(__('লেআউট'))->options(['carousel' => __('ক্যারোসেল'), 'grid' => __('গ্রিড')])->default('carousel'),
            ]),
            self::block('reviews', __('রিভিউ'), 'heroicon-o-chat-bubble-left-right', [
                TextInput::make('heading')->label(__('শিরোনাম'))->default(__('সম্মানিত কাস্টমার রিভিউ'))->maxLength(255)->columnSpanFull(),
                ImageUpload::make('images')->label(__('রিভিউ স্ক্রিনশট'))->multiple()->reorderable()->columnSpanFull(),
                TextInput::make('button_text')->label(__('বাটনের লেখা (খালি = বাটন নেই)'))->maxLength(100),
            ]),
            self::block('video', __('ভিডিও'), 'heroicon-o-play-circle', [
                TextInput::make('heading')->label(__('শিরোনাম'))->maxLength(255)->columnSpanFull(),
                self::videoLink('url')->label(__('ভিডিও লিংক (YouTube / Facebook / MP4)'))->required(),
                ImageUpload::make('poster')->label(__('পোস্টার ছবি')),
            ]),
            self::block('variants', __('সাইজ / কালার'), 'heroicon-o-swatch', [
                TextInput::make('label')->label(__('ছোট লেবেল (ঐচ্ছিক)'))->maxLength(100),
                TextInput::make('heading')->label(__('শিরোনাম'))->maxLength(255),
                TextInput::make('accent')->label(__('রঙিন দ্বিতীয় লাইন (ঐচ্ছিক)'))->maxLength(255)->columnSpanFull(),
                Textarea::make('description')->label(__('বর্ণনা (ঐচ্ছিক)'))->rows(2)->columnSpanFull(),
                Repeater::make('options')->label(__('ছবিসহ কালার/ডিজাইন গ্রিড (প্রতিটায় অর্ডার বাটন)'))
                    ->schema([
                        ImageUpload::make('image')->label(__('ছবি')),
                        TextInput::make('name')->label(__('নাম'))->required()->maxLength(100),
                    ])->columns(2)->reorderable()->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->addActionLabel(__('কালার/ডিজাইন যোগ করুন'))->defaultItems(0)->columnSpanFull(),
                TextInput::make('button_text')->label(__('গ্রিডের বাটনের লেখা'))->default(__('অর্ডার করুন'))->maxLength(100),
                Toggle::make('size_enabled')->label(__('অর্ডার ফর্মে সাইজের ঘর দেখাও'))->default(true)->live()->columnSpanFull(),
                TextInput::make('size_label')->label(__('সাইজের লেবেল'))->default(__('সাইজ নির্বাচন করুন'))->maxLength(100),
                TagsInput::make('sizes')->label(__('সাইজের অপশন (খালি = নিজে লিখবে)')),
                Toggle::make('size_required')->label(__('সাইজ আবশ্যক'))->default(true),
                Toggle::make('color_enabled')->label(__('অর্ডার ফর্মে কালারের ঘর দেখাও'))->default(true)->live()->columnSpanFull(),
                TextInput::make('color_label')->label(__('কালারের লেবেল'))->default(__('কালার নির্বাচন করুন'))->maxLength(100),
                TagsInput::make('colors')->label(__('কালারের অপশন (খালি = নিজে লিখবে; গ্রিডের নামও অপশন)')),
                Toggle::make('color_required')->label(__('কালার আবশ্যক'))->default(false),
            ]),
            self::block('models', __('মডেল / রঙের ছবি-গ্রিড'), 'heroicon-o-squares-2x2', [
                TextInput::make('label')->label(__('ছোট লেবেল (ঐচ্ছিক)'))->placeholder('MODELS')->maxLength(100),
                TextInput::make('heading')->label(__('শিরোনাম'))->placeholder(__('আপনার পছন্দের মডেল বেছে নিন'))->maxLength(255),
                TextInput::make('accent')->label(__('রঙিন দ্বিতীয় লাইন (ঐচ্ছিক)'))->maxLength(255)->columnSpanFull(),
                Textarea::make('description')->label(__('বর্ণনা (ঐচ্ছিক)'))->rows(2)->columnSpanFull(),
                TextInput::make('button_text')->label(__('প্রতিটি কার্ডের বাটনের লেখা'))->default(__('অর্ডার করুন'))->maxLength(100),
                Text::make(__('কার্ডগুলো আসে পণ্যের রং/সাইজ (variant) থেকে — প্রতিটির নিজের ছবি, দাম ও স্টক। প্যাকেজ দিলে প্যাকেজই দেখাবে।'))->columnSpanFull(),
            ]),
            self::block('price', __('দাম ও ছাড়'), 'heroicon-o-tag', [
                TextInput::make('label')->label(__('ছোট ব্যাজ (ঐচ্ছিক)'))->placeholder(__('সীমিত সময়ের অফার'))->maxLength(100),
                TextInput::make('heading')->label(__('শিরোনাম (ঐচ্ছিক)'))->maxLength(255),
                TextInput::make('note')->label(__('নিচের ছোট লেখা (ঐচ্ছিক)'))->placeholder(__('ক্যাশ অন ডেলিভারি — আগে টাকা লাগবে না'))->maxLength(255)->columnSpanFull(),
                TextInput::make('button_text')->label(__('বাটনের লেখা (খালি = বাটন নেই)'))->default(__('অর্ডার করুন'))->maxLength(100),
                Text::make(__('দাম, আগের দাম আর ছাড় নিজে থেকেই পণ্য/পেজের দাম থেকে হিসাব হয়।'))->columnSpanFull(),
            ]),
            self::block('trust', __('গ্যারান্টি / বিশ্বাস'), 'heroicon-o-shield-check', [
                TextInput::make('heading')->label(__('শিরোনাম'))->placeholder(__('কেন আমাদের উপর ভরসা করবেন?'))->maxLength(255)->columnSpanFull(),
                Repeater::make('items')->label(__('কার্ড'))
                    ->schema([
                        Select::make('icon')->label(__('আইকন'))->options([
                            '🛡️' => __('🛡️ গ্যারান্টি'), '🚚' => __('🚚 ডেলিভারি'), '🔄' => __('🔄 রিটার্ন/এক্সচেঞ্জ'),
                            '📞' => __('📞 সাপোর্ট'), '💵' => __('💵 ক্যাশ অন ডেলিভারি'), '⭐' => __('⭐ মান'), '✅' => __('✅ আসল পণ্য'), '🎁' => __('🎁 উপহার'),
                        ])->default('🛡️')->required(),
                        TextInput::make('title')->label(__('শিরোনাম'))->required()->maxLength(255),
                        TextInput::make('description')->label(__('বিবরণ'))->maxLength(500)->columnSpanFull(),
                    ])->columns(2)->reorderable()->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->addActionLabel(__('কার্ড যোগ করুন'))->defaultItems(0)->columnSpanFull(),
            ]),
            self::block('cta', __('কল-টু-অ্যাকশন (কল)'), 'heroicon-o-phone', [
                TextInput::make('heading')->label(__('লেখা'))->placeholder(__('বিশেষ প্রয়োজনে কল করুনঃ'))->maxLength(255)->columnSpanFull(),
                Textarea::make('subtext')->label(__('ছোট বর্ণনা (ঐচ্ছিক)'))->rows(2)->columnSpanFull(),
                TextInput::make('phone')->label(__('ফোন নম্বর (ঐচ্ছিক)'))->tel()->maxLength(30),
                TextInput::make('button_text')->label(__('বাটনের লেখা (খালি = বাটন নেই)'))->default(__('অর্ডার করুন'))->maxLength(100),
            ]),
            self::block('notice', __('গুরুত্বপূর্ণ বিষয়'), 'heroicon-o-exclamation-circle', [
                TextInput::make('heading')->label(__('শিরোনাম'))->placeholder(__('২ টি গুরুত্বপূর্ণ বিষয়ঃ'))->maxLength(255)->columnSpanFull(),
                Repeater::make('items')->label(__('পয়েন্ট'))
                    ->simple(TextInput::make('text')->required()->maxLength(500))
                    ->reorderable()->addActionLabel(__('পয়েন্ট যোগ করুন'))->defaultItems(0)->columnSpanFull(),
            ]),
            self::block('order_form', __('অর্ডার ফর্ম'), 'heroicon-o-shopping-bag', [
                TextInput::make('pill')->label(__('ছোট ব্যাজ (ঐচ্ছিক)'))->placeholder(__('কোনো অগ্রিম টাকা লাগবে না'))->maxLength(150)->columnSpanFull(),
                TextInput::make('heading')->label(__('শিরোনাম'))->placeholder(__('সঠিক তথ্য দিয়ে নিচের ফর্মটি পূরণ করুন'))->maxLength(255)->columnSpanFull(),
                Textarea::make('subtext')->label(__('বর্ণনা (ঐচ্ছিক)'))->rows(2)->columnSpanFull(),
                TextInput::make('phone')->label(__('ফোন নম্বর (ঐচ্ছিক)'))->tel()->maxLength(30),
                TextInput::make('button_text')->label(__('অর্ডার বাটনের লেখা'))->default(__('অর্ডার কনফার্ম করুন'))->maxLength(100),
                Textarea::make('note')->label(__('ফর্মের নিচের নোট'))->rows(2)->columnSpanFull(),
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
                    ->limit(60)
                    ->extraCellAttributes(['style' => 'min-width: 13rem']),
                TextColumn::make('slug')
                    ->visibleFrom('lg')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('template')
                    ->visibleFrom('lg')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => __(LandingPage::TEMPLATE_OPTIONS[$state] ?? (string) $state))
                    ->toggleable(),
                TextColumn::make('product.name')
                    ->visibleFrom('md')
                    ->label(__('পণ্য'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('views')
                    ->label(__('ভিজিট'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('orders_count')
                    ->label(__('অর্ডার'))
                    ->counts(['orders' => fn ($query) => $query->counted()])
                    ->badge()
                    ->sortable(),
                TextColumn::make('conversion')
                    ->visibleFrom('md')
                    ->toggleable()
                    ->label(__('কনভার্শন'))
                    ->state(fn (LandingPage $record): string => $record->views > 0
                        ? number_format($record->orders_count / $record->views * 100, 1).'%'
                        : '—')
                    ->color(fn (LandingPage $record): string => $record->views > 0 && $record->orders_count / $record->views >= 0.03 ? 'success' : 'gray')
                    ->badge()
                    ->tooltip(__('অর্ডার ÷ ভিজিট')),
                TextColumn::make('revenue')
                    ->visibleFrom('md')
                    ->toggleable()
                    ->label(__('বিক্রি'))
                    ->state(fn (LandingPage $record): string => '৳ '.number_format((float) $record->orders()->counted()->sum('total'), 0)),
                IconColumn::make('is_active')
                    ->label(__('চালু'))
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
                    ->options(array_map('__', LandingPage::TEMPLATE_OPTIONS)),
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                RowMenu::make([
                    Action::make('preview')
                        ->label(__('প্রিভিউ'))
                        ->icon(Heroicon::OutlinedEye)
                        ->url(fn (LandingPage $record): string => route('landing.show', $record->slug))
                        ->openUrlInNewTab(),
                    self::duplicateAction(),
                    DeleteAction::make(),
                ]),
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
            ->label(__('কপি করুন'))
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('এই পেজের একটি কপি (inactive অবস্থায়) তৈরি হবে। ভিউ ও অর্ডারের সংখ্যা কপি হয় না।'))
            ->action(function (LandingPage $record) {
                $copy = $record->duplicate();

                Notification::make()->title(__('Duplicated — ইনঅ্যাকটিভ কপি তৈরি হয়েছে।'))->success()->send();

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
