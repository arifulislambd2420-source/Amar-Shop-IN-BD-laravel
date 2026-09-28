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
use Illuminate\Support\Facades\Crypt;
use UnitEnum;

/**
 * Courier settings — Steadfast API credentials stored in site_settings.
 *
 * steadfast_secret_key is stored Crypt::encryptString()'d (see save()).
 * SteadfastCourierService decrypts it when actually calling the API; this
 * page itself never reads the stored secret back into the form (see
 * mount()) — leaving the field blank on save always keeps the current
 * value, typing a new value is the only way to change it.
 */
class CourierSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Courier';

    protected static ?string $title = 'Courier (Steadfast)';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public ?array $data = [];

    protected array $keys = ['steadfast_api_key', 'steadfast_secret_key'];

    public function mount(): void
    {
        $values = SiteSetting::whereIn('setting_key', $this->keys)
            ->pluck('setting_value', 'setting_key')
            ->all();

        $this->form->fill([
            'steadfast_api_key' => $values['steadfast_api_key'] ?? '',
            // Never prefill the real secret — the field always starts
            // blank. Leaving it blank on save keeps the stored value
            // unchanged (see save()); typing something new replaces it.
            'steadfast_secret_key' => '',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Steadfast Courier')
                    ->schema([
                        TextInput::make('steadfast_api_key')
                            ->label('API key')
                            ->placeholder('Enter your Steadfast API-Key'),
                        TextInput::make('steadfast_secret_key')
                            ->label('Secret key')
                            ->password()
                            ->revealable()
                            ->helperText('Leave blank to keep the current secret.')
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if ($key === 'steadfast_secret_key') {
                if (blank($value)) {
                    // Blank means "keep the current value" — skip writing.
                    continue;
                }

                $value = Crypt::encryptString($value);
            }

            SiteSetting::updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $value],
            );
        }

        Notification::make()->title('Courier settings saved.')->success()->send();
    }
}
