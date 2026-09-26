<?php

namespace App\Filament\Pages;

use App\Filament\Support\CloudinaryUpload;
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
 * Site Setting — logo, favicon, site name (stored in the site_settings
 * key/value table, matching the old app).
 */
class SiteSettings extends Page implements HasSchemas
{
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
    protected array $keys = ['site_logo', 'site_favicon', 'site_name'];

    public function mount(): void
    {
        $values = SiteSetting::whereIn('setting_key', $this->keys)
            ->pluck('setting_value', 'setting_key')
            ->all();

        $this->form->fill([
            'site_logo' => $values['site_logo'] ?? '',
            'site_favicon' => $values['site_favicon'] ?? '',
            'site_name' => $values['site_name'] ?? '',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Site Setting')
                    ->schema([
                        TextInput::make('site_name')
                            ->label('Site name')
                            ->placeholder('আমারশপ'),
                        CloudinaryUpload::make('site_logo')
                            ->label('Site logo'),
                        CloudinaryUpload::make('site_favicon')
                            ->label('Favicon')
                            ->helperText('Falls back to the logo when empty. Uploads to Cloudinary (folder: amarshopbd).'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            SiteSetting::updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $value],
            );
        }

        Notification::make()->title('Site settings saved.')->success()->send();
    }
}
