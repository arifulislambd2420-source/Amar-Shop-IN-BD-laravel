<?php

namespace App\Filament\Pages;

use App\Models\FraudApiConfig;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Fraud API settings — two configs (free / paid) stored in the
 * fraud_api_configs table, matching the old FraudApiAdmin.
 */
class FraudApiSettings extends Page implements HasSchemas
{
    use \App\Filament\Concerns\HasAdminArea;

    protected static string $adminArea = \App\Support\AdminAccess::AREA_SETTINGS;

    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Fraud API';

    protected static ?string $title = 'Fraud API';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $free = FraudApiConfig::where('type', 'free')->first();
        $paid = FraudApiConfig::where('type', 'paid')->first();

        $this->form->fill([
            'free_api_url' => $free?->api_url ?? '',
            'free_api_key' => $free?->api_key ?? '',
            'free_active' => (bool) ($free?->active ?? false),
            'paid_api_url' => $paid?->api_url ?? '',
            'paid_api_key' => $paid?->api_key ?? '',
            'paid_active' => (bool) ($paid?->active ?? false),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Free fraud-check API')
                    ->schema([
                        TextInput::make('free_api_url')
                            ->label('API URL')
                            ->placeholder('https://example.com/api/check'),
                        TextInput::make('free_api_key')
                            ->label('API key')
                            ->password()
                            ->revealable(),
                        Toggle::make('free_active')->label('Active'),
                    ]),
                Section::make('Paid fraud-check API')
                    ->schema([
                        TextInput::make('paid_api_url')
                            ->label('API URL')
                            ->placeholder('https://example.com/api/check'),
                        TextInput::make('paid_api_key')
                            ->label('API key')
                            ->password()
                            ->revealable(),
                        Toggle::make('paid_active')->label('Active'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        FraudApiConfig::updateOrCreate(
            ['type' => 'free'],
            [
                'api_url' => $data['free_api_url'] ?? null,
                'api_key' => $data['free_api_key'] ?? null,
                'active' => (bool) ($data['free_active'] ?? false),
            ],
        );

        FraudApiConfig::updateOrCreate(
            ['type' => 'paid'],
            [
                'api_url' => $data['paid_api_url'] ?? null,
                'api_key' => $data['paid_api_key'] ?? null,
                'active' => (bool) ($data['paid_active'] ?? false),
            ],
        );

        Notification::make()->title('Fraud API settings saved.')->success()->send();
    }
}
