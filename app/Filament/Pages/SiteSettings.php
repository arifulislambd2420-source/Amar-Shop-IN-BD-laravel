<?php

namespace App\Filament\Pages;

use App\Filament\Support\ImageUpload;
use App\Models\SiteSetting;
use App\Support\SiteSettingsHelper;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Site Setting — logo, favicon, site name (stored in the site_settings
 * key/value table, matching the old app).
 */
class SiteSettings extends Page implements HasSchemas
{
    use \App\Filament\Concerns\HasAdminArea;

    protected static string $adminArea = \App\Support\AdminAccess::AREA_SETTINGS;

    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Site Setting';

    protected static ?string $title = 'Site Setting';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Keys managed by this page. */
    protected array $keys = [
        'site_logo', 'site_favicon', 'site_name', 'site_name_en',
        'footer_description',
        'hero_title', 'hero_subtitle',
        'side_card_1_title', 'side_card_1_text', 'side_card_2_title', 'side_card_2_text',
        'seo_title', 'seo_description', 'og_image',
        'order_cooldown_minutes', 'low_stock_threshold',
        'delivery_fee_dhaka', 'delivery_fee_outside', 'delivery_free_min',
        'color_brand', 'color_secondary', 'color_accent', 'color_background', 'color_text', 'color_success', 'color_error',
        'contact_phone', 'contact_whatsapp', 'contact_email', 'contact_address',
        'social_facebook', 'social_youtube', 'social_instagram', 'social_tiktok',
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

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Site Setting')
                    ->schema([
                        TextInput::make('site_name')
                            ->label('Site name (Bangla)')
                            ->placeholder('আমারশপ')
                            ->helperText('Header and footer logo text (when no logo image is set).'),
                        TextInput::make('site_name_en')
                            ->label('Site name (English)')
                            ->placeholder('Amar Shop in BD')
                            ->helperText('Used in the browser title and the footer copyright line.'),
                        ImageUpload::make('site_logo')
                            ->label('Site logo'),
                        ImageUpload::make('site_favicon')
                            ->label('Favicon')
                            ->helperText('Falls back to the logo when empty.'),
                    ]),
                Section::make('রং (Colors)')
                    ->description('সাইটের রং। ডিফল্ট রং বদলাতে না চাইলে অপরিবর্তিত রাখুন। Admin প্যানেলের primary রংও ব্র্যান্ড রং থেকে আসে।')
                    ->headerActions([
                        Action::make('resetColors')
                            ->label('ডিফল্টে ফেরত')
                            ->color('gray')
                            ->requiresConfirmation()
                            ->modalDescription('সব রং আগের ডিফল্ট রঙে ফিরে যাবে।')
                            ->action(fn () => $this->resetColors()),
                    ])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                    ->schema([
                        ColorPicker::make('color_brand')->label('ব্র্যান্ড (বাটন, লিংক, হাইলাইট)'),
                        ColorPicker::make('color_secondary')->label('সেকেন্ডারি (ফুটার, গাঢ় অংশ)'),
                        ColorPicker::make('color_accent')->label('অ্যাকসেন্ট (রেটিং তারা ইত্যাদি)'),
                        ColorPicker::make('color_background')->label('ব্যাকগ্রাউন্ড'),
                        ColorPicker::make('color_text')->label('টেক্সট'),
                        ColorPicker::make('color_success')->label('সফল'),
                        ColorPicker::make('color_error')->label('এরর'),
                    ]),
                Section::make('অর্ডার ও স্টক')
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
                    ->description('চেকআউটে জেলা অনুযায়ী ডেলিভারি চার্জ বসে। চার্জ খালি রাখলে ডিফল্ট (' . config('site.delivery.dhaka') . ' / ' . config('site.delivery.outside') . ' টাকা) প্রযোজ্য।')
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
                Section::make('Footer')
                    ->description('Empty fields keep the current built-in text.')
                    ->schema([
                        Textarea::make('footer_description')
                            ->label('Footer description')
                            ->rows(3)
                            ->maxLength(500),
                    ]),
                Section::make('Home page text')
                    ->description('Shown on the home page when no hero banner / side banner is uploaded. Empty fields keep the current built-in text.')
                    ->schema([
                        TextInput::make('hero_title')->label('Hero title')->maxLength(200),
                        TextInput::make('hero_subtitle')->label('Hero subtitle')->maxLength(300),
                        TextInput::make('side_card_1_title')->label('Side card 1 — title')->maxLength(120),
                        TextInput::make('side_card_1_text')->label('Side card 1 — text')->maxLength(200),
                        TextInput::make('side_card_2_title')->label('Side card 2 — title')->maxLength(120),
                        TextInput::make('side_card_2_text')->label('Side card 2 — text')->maxLength(200),
                    ]),
                Section::make('SEO')
                    ->description('Default title/description for pages that do not set their own. Empty fields keep the current built-in text.')
                    ->schema([
                        TextInput::make('seo_title')
                            ->label('SEO title')
                            ->maxLength(70),
                        Textarea::make('seo_description')
                            ->label('Meta description')
                            ->rows(3)
                            ->maxLength(300),
                        ImageUpload::make('og_image')
                            ->label('OG image (social share)')
                            ->helperText('Recommended 1200×630.'),
                    ]),
                Section::make('Contact')
                    ->description('Shown in the header, footer, About page and invoice. Empty fields fall back to the defaults in config/site.php.')
                    ->schema([
                        TextInput::make('contact_phone')
                            ->label('Phone')
                            ->placeholder('+880 1874-783819')
                            ->maxLength(30),
                        TextInput::make('contact_whatsapp')
                            ->label('WhatsApp number')
                            ->placeholder('8801874783819')
                            ->helperText('With country code, e.g. 8801XXXXXXXXX.')
                            ->maxLength(30),
                        TextInput::make('contact_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('contact_address')
                            ->label('Address')
                            ->maxLength(500),
                    ]),
                Section::make('Social links')
                    ->description('Leave a field empty to hide that link from the footer.')
                    ->schema([
                        TextInput::make('social_facebook')->label('Facebook')->url()->maxLength(500),
                        TextInput::make('social_youtube')->label('YouTube')->url()->maxLength(500),
                        TextInput::make('social_instagram')->label('Instagram')->url()->maxLength(500),
                        TextInput::make('social_tiktok')->label('TikTok')->url()->maxLength(500),
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

            SiteSetting::updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => (string) $value],
            );

            SiteSettingsHelper::forget($key);
        }

        Notification::make()->title('Site settings saved.')->success()->send();
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
