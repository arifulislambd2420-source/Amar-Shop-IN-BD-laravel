<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use BackedEnum;
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
 * GTM / Pixel settings — stores the gtm_id key in site_settings.
 */
class GtmSettings extends Page implements HasSchemas
{
    use \App\Filament\Concerns\HasAdminArea;

    protected static string $adminArea = \App\Support\AdminAccess::AREA_SETTINGS;

    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'GTM / Pixel';

    protected static ?string $title = 'GTM / Pixel';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'gtm_id' => SiteSetting::where('setting_key', 'gtm_id')->value('setting_value') ?? '',
            'meta_pixel_id' => SiteSetting::where('setting_key', 'meta_pixel_id')->value('setting_value') ?? '',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Google Tag Manager / Meta Pixel')
                    ->schema([
                        TextInput::make('gtm_id')
                            ->label('GTM container ID')
                            ->placeholder('GTM-XXXXXXX'),
                        TextInput::make('meta_pixel_id')
                            ->label('Meta (Facebook) Pixel ID')
                            ->placeholder('1234567890123456')
                            ->helperText('চালু থাকলে সারা সাইটে (ল্যান্ডিং পেজসহ) PageView, ViewContent, AddToCart, InitiateCheckout ও Purchase যায়। খালি = বন্ধ।'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteSetting::updateOrCreate(
            ['setting_key' => 'gtm_id'],
            ['setting_value' => trim((string) ($data['gtm_id'] ?? ''))],
        );

        SiteSetting::updateOrCreate(
            ['setting_key' => 'meta_pixel_id'],
            ['setting_value' => preg_replace('/\D+/', '', (string) ($data['meta_pixel_id'] ?? ''))],
        );

        Notification::make()->title('GTM / Pixel settings saved.')->success()->send();
    }
}
