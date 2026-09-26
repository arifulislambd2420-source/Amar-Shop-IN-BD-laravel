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

        Notification::make()->title('GTM settings saved.')->success()->send();
    }
}
