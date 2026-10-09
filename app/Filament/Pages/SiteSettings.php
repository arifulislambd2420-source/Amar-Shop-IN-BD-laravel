<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminArea;
use App\Filament\Support\ImageUpload;
use App\Models\Category;
use App\Models\SiteSetting;
use App\Support\AdminAccess;
use App\Support\HomeSections;
use App\Support\SiteSettingsHelper;
use App\Support\StorefrontCache;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * সাইট সেটিং — everything a shop owner changes without touching code:
 * identity (name, logo, footer logo, favicon), colours, which home-page
 * sections show and in what order, texts, footer, contact details, social
 * links, orders/stock, delivery and SEO. Stored in the site_settings
 * key/value table.
 */
class SiteSettings extends Page implements HasSchemas
{
    use HasAdminArea;

    protected static string $adminArea = AdminAccess::AREA_SETTINGS;

    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'সাইট সেটিং';

    protected static ?string $title = 'সাইট সেটিং';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Settings stored as JSON (lists), handled separately from plain text values. */
    private const JSON_KEYS = ['home_sections', 'home_showcase_categories'];

    /** Keys managed by this page. */
    protected array $keys = [
        'site_logo', 'footer_logo', 'site_favicon', 'site_name', 'site_name_en',
        'footer_description', 'footer_copyright',
        'hero_title', 'hero_subtitle',
        'side_card_1_title', 'side_card_1_text', 'side_card_2_title', 'side_card_2_text',
        'seo_title', 'seo_description', 'og_image',
        'order_cooldown_minutes', 'low_stock_threshold',
        'delivery_fee_dhaka', 'delivery_fee_outside', 'delivery_free_min',
        'color_brand', 'color_secondary', 'color_accent', 'color_background', 'color_text', 'color_success', 'color_error',
        'contact_phone', 'contact_whatsapp', 'contact_email', 'contact_address', 'contact_hours',
        'social_facebook', 'social_youtube', 'social_instagram', 'social_tiktok', 'social_twitter', 'social_linkedin', 'social_telegram',
        'home_sections', 'home_showcase_categories',
    ];

    public function mount(): void
    {
        $values = SiteSetting::whereIn('setting_key', $this->keys)
            ->pluck('setting_value', 'setting_key')
            ->all();

        $state = collect($this->keys)->mapWithKeys(fn ($key) => [$key => $values[$key] ?? ''])->all();

        // Colors always show the color currently in use (the default when unset).
        foreach (array_keys(SiteSettingsHelper::COLOR_VARS) as $name) {
            $state["color_{$name}"] = SiteSettingsHelper::color($name);
        }

        // The sections list always shows every section, in the saved order.
        $state['home_sections'] = HomeSections::all();
        $state['home_showcase_categories'] = HomeSections::showcaseCategoryIds();

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('সাইটের পরিচয়')
                    ->description('দোকানের নাম আর লোগো। লোগো না দিলে নামটাই লেখা আকারে দেখাবে।')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('site_name')
                            ->label('দোকানের নাম (বাংলা)')
                            ->placeholder('আমারশপ')
                            ->helperText('হেডার ও ফুটারে লোগোর বদলে দেখায় (লোগো না থাকলে)।'),
                        TextInput::make('site_name_en')
                            ->label('দোকানের নাম (ইংরেজি)')
                            ->placeholder('Amar Shop in BD')
                            ->helperText('ব্রাউজারের টাইটেল ও ফুটারের কপিরাইট লাইনে ব্যবহার হয়।'),
                        ImageUpload::make('site_logo')
                            ->label('সাইটের লোগো (হেডার)')
                            ->helperText('সাদা/হালকা পটভূমিতে মানানসই লোগো। ছবি: JPG, PNG বা WEBP।'),
                        ImageUpload::make('footer_logo')
                            ->label('ফুটারের লোগো')
                            ->helperText('ফুটারের পটভূমি গাঢ়, তাই এখানে সাদা/হালকা রঙের লোগো দিন। খালি থাকলে দোকানের নাম লেখা দেখাবে।'),
                        ImageUpload::make('site_favicon')
                            ->label('ফেভিকন (ব্রাউজার ট্যাবের ছোট আইকন)')
                            ->helperText('খালি থাকলে হেডারের লোগো ব্যবহার হবে। বর্গাকার ছবি ভালো।'),
                    ]),
                Section::make('রং')
                    ->description('সাইটের রং। ডিফল্ট রং বদলাতে না চাইলে অপরিবর্তিত রাখুন। অ্যাডমিন প্যানেলের মূল রংও ব্র্যান্ড রং থেকে আসে।')
                    ->headerActions([
                        Action::make('resetColors')
                            ->label('ডিফল্টে ফেরত')
                            ->color('gray')
                            ->requiresConfirmation()
                            ->modalHeading('রং ডিফল্টে ফেরত নেবেন?')
                            ->modalDescription('সব রং আগের ডিফল্ট রঙে ফিরে যাবে।')
                            ->modalSubmitActionLabel('হ্যাঁ, ফেরত নিন')
                            ->modalCancelActionLabel('বাতিল')
                            ->action(fn () => $this->resetColors()),
                    ])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                    ->schema([
                        ColorPicker::make('color_brand')->label('প্রাইমারি / ব্র্যান্ড (বাটন, লিংক, হাইলাইট)'),
                        ColorPicker::make('color_secondary')->label('সেকেন্ডারি (ফুটার, গাঢ় অংশ)'),
                        ColorPicker::make('color_accent')->label('অ্যাকসেন্ট (রেটিং তারা ইত্যাদি)'),
                        ColorPicker::make('color_background')->label('ব্যাকগ্রাউন্ড'),
                        ColorPicker::make('color_text')->label('লেখা'),
                        ColorPicker::make('color_success')->label('সফল'),
                        ColorPicker::make('color_error')->label('ত্রুটি'),
                    ]),
                Section::make('হোমপেজের সেকশন')
                    ->description('কোন সেকশন হোমপেজে দেখাবে আর কোনটার পর কোনটা — সুইচ দিয়ে চালু/বন্ধ করুন, তীর দিয়ে ওঠানামা করান।')
                    ->schema([
                        Repeater::make('home_sections')
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderableWithButtons()
                            ->schema([
                                Hidden::make('key'),
                                Hidden::make('label'),
                                Toggle::make('on')
                                    ->label(fn (Get $get): string => (string) $get('label'))
                                    ->default(true),
                            ]),
                        Select::make('home_showcase_categories')
                            ->label('"ক্যাটাগরি অনুযায়ী পণ্য" সেকশনে কোন ক্যাটাগরি দেখাবে')
                            ->multiple()
                            ->options(fn () => Category::orderBy('name')->pluck('name', 'id')->all())
                            ->helperText('যে ক্রমে বাছবেন সেই ক্রমে দেখাবে। কিছু না বাছলে পণ্য আছে এমন প্রথম দুটি ক্যাটাগরি দেখাবে।'),
                    ]),
                Section::make('হোমপেজের লেখা')
                    ->description('হিরো ব্যানার বা সাইড ব্যানার না থাকলে হোমপেজে এই লেখাগুলো দেখায়। খালি রাখলে বিল্ট-ইন লেখাই থাকবে।')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('hero_title')->label('হিরো শিরোনাম')->maxLength(200),
                        TextInput::make('hero_subtitle')->label('হিরো উপশিরোনাম')->maxLength(300),
                        TextInput::make('side_card_1_title')->label('সাইড কার্ড ১ — শিরোনাম')->maxLength(120),
                        TextInput::make('side_card_1_text')->label('সাইড কার্ড ১ — লেখা')->maxLength(200),
                        TextInput::make('side_card_2_title')->label('সাইড কার্ড ২ — শিরোনাম')->maxLength(120),
                        TextInput::make('side_card_2_text')->label('সাইড কার্ড ২ — লেখা')->maxLength(200),
                    ]),
                Section::make('ফুটার')
                    ->description('খালি রাখলে বিল্ট-ইন লেখাই থাকবে।')
                    ->schema([
                        Textarea::make('footer_description')
                            ->label('ফুটারের বিবরণ (দোকানের পরিচিতি)')
                            ->rows(3)
                            ->maxLength(500),
                        TextInput::make('footer_copyright')
                            ->label('কপিরাইট লাইন')
                            ->placeholder('© ২০২৬ আমারশপ — সব অধিকার সংরক্ষিত')
                            ->helperText('খালি থাকলে দোকানের নাম দিয়ে নিজে থেকে তৈরি হবে।')
                            ->maxLength(300),
                    ]),
                Section::make('যোগাযোগের তথ্য')
                    ->description('হেডার, ফুটার, আমাদের সম্পর্কে পেজ আর ইনভয়েসে দেখায়। খালি রাখলে ডিফল্ট তথ্য থাকবে।')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('contact_phone')
                            ->label('ফোন')
                            ->placeholder('+880 1874-783819')
                            ->maxLength(30),
                        TextInput::make('contact_whatsapp')
                            ->label('হোয়াটসঅ্যাপ নম্বর')
                            ->placeholder('8801874783819')
                            ->helperText('দেশের কোডসহ, যেমন 8801XXXXXXXXX। খালি রাখলে হোয়াটসঅ্যাপ বাটন দেখাবে না।')
                            ->maxLength(30),
                        TextInput::make('contact_email')
                            ->label('ইমেইল')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('contact_address')
                            ->label('ঠিকানা')
                            ->maxLength(500),
                        TextInput::make('contact_hours')
                            ->label('খোলার সময়')
                            ->placeholder('শনি–বৃহস্পতি, সকাল ১০টা – রাত ৯টা')
                            ->helperText('ফুটারে দেখায়। খালি রাখলে দেখাবে না।')
                            ->maxLength(200)
                            ->columnSpanFull(),
                    ]),
                Section::make('সোশ্যাল লিংক')
                    ->description('যে ঘর খালি থাকবে, ফুটারে তার লিংক দেখাবে না।')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema(collect(SiteSettingsHelper::SOCIALS)
                        ->map(fn (string $label, string $network) => TextInput::make("social_{$network}")
                            ->label($label)
                            ->url()
                            ->placeholder('https://')
                            ->maxLength(500))
                        ->values()
                        ->all()),
                Section::make('অর্ডার ও স্টক')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('order_cooldown_minutes')
                            ->label('একই ফোন থেকে পরপর অর্ডারের বিরতি (মিনিট)')
                            ->numeric()->minValue(0)->maxValue(1440)
                            ->placeholder('10')
                            ->helperText('এই সময়ের মধ্যে একই ফোন থেকে আরেকটি অর্ডার নেওয়া হবে না। খালি = ১০ মিনিট, ০ = বন্ধ।'),
                        TextInput::make('low_stock_threshold')
                            ->label('কম স্টকের সীমা (পিস)')
                            ->numeric()->minValue(0)->maxValue(100000)
                            ->placeholder('5')
                            ->helperText('স্টক এর সমান বা কম হলে ড্যাশবোর্ডে সতর্কতা আসবে। খালি = ৫।'),
                    ]),
                Section::make('ডেলিভারি')
                    ->description('চেকআউটে জেলা অনুযায়ী ডেলিভারি চার্জ বসে। চার্জ খালি রাখলে ডিফল্ট ('.config('site.delivery.dhaka').' / '.config('site.delivery.outside').' টাকা) প্রযোজ্য।')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        TextInput::make('delivery_fee_dhaka')
                            ->label('ঢাকার ভেতরে চার্জ (৳)')
                            ->numeric()->minValue(0)->maxValue(100000)
                            ->placeholder((string) config('site.delivery.dhaka')),
                        TextInput::make('delivery_fee_outside')
                            ->label('ঢাকার বাইরে চার্জ (৳)')
                            ->numeric()->minValue(0)->maxValue(100000)
                            ->placeholder((string) config('site.delivery.outside')),
                        TextInput::make('delivery_free_min')
                            ->label('ফ্রি ডেলিভারির ন্যূনতম অর্ডার (৳)')
                            ->numeric()->minValue(0)->maxValue(10000000)
                            ->helperText('খালি = ফ্রি ডেলিভারি বন্ধ। কুপন ছাড়ের আগের সাবটোটাল ধরা হয়।'),
                    ]),
                Section::make('এসইও (সার্চ ইঞ্জিন)')
                    ->description('যে পেজ নিজের টাইটেল/বিবরণ দেয়নি তার ডিফল্ট। খালি রাখলে বিল্ট-ইন লেখাই থাকবে।')
                    ->schema([
                        TextInput::make('seo_title')
                            ->label('এসইও টাইটেল')
                            ->maxLength(70),
                        Textarea::make('seo_description')
                            ->label('মেটা বিবরণ')
                            ->rows(3)
                            ->maxLength(300),
                        ImageUpload::make('og_image')
                            ->label('শেয়ারের ছবি (ফেসবুক ইত্যাদিতে)')
                            ->helperText('১২০০×৬৩০ আকারের ছবি ভালো।'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            // A color equal to its default is stored empty, so the site keeps
            // following the default if it ever changes.
            if (str_starts_with($key, 'color_')) {
                $name = substr($key, 6);
                $value = strtolower((string) $value) === strtolower(SiteSettingsHelper::colorDefaults()[$name] ?? '') ? '' : $value;
            }

            $value = in_array($key, self::JSON_KEYS, true) ? $this->encodeList($key, $value) : (string) $value;

            SiteSetting::updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $value],
            );

            SiteSettingsHelper::forget($key);
        }

        StorefrontCache::flush();

        Notification::make()->title('সাইট সেটিং সেভ হয়েছে।')->success()->send();
    }

    /** JSON for a list setting; '' (= default behaviour) when there is nothing to store. */
    private function encodeList(string $key, mixed $value): string
    {
        $value = is_array($value) ? array_values($value) : [];

        if ($key === 'home_sections') {
            $value = array_values(array_filter(array_map(
                fn ($row) => is_array($row) && isset(HomeSections::SECTIONS[$row['key'] ?? ''])
                    ? ['key' => $row['key'], 'on' => (bool) ($row['on'] ?? false)]
                    : null,
                $value,
            )));
        } else {
            $value = array_values(array_unique(array_map('intval', $value)));
        }

        return $value === [] ? '' : (string) json_encode($value);
    }

    /** Drop every saved color and show the defaults in the form. */
    public function resetColors(): void
    {
        foreach (SiteSettingsHelper::colorDefaults() as $name => $default) {
            SiteSetting::where('setting_key', "color_{$name}")->delete();
            SiteSettingsHelper::forget("color_{$name}");
            $this->data["color_{$name}"] = $default;
        }

        Notification::make()->title('রং ডিফল্টে ফিরেছে।')->success()->send();
    }
}
