<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use BackedEnum;
use Filament\Forms\Components\Select;
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
 * Mail (SMTP) settings — stored as mail_* keys in the site_settings table,
 * matching the old SmtpAdmin.
 */
class MailSettings extends Page implements HasSchemas
{
    use \App\Filament\Concerns\HasAdminArea;

    protected static string $adminArea = \App\Support\AdminAccess::AREA_SETTINGS;

    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Mail SMTP';

    protected static ?string $title = 'Mail SMTP';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public ?array $data = [];

    protected array $keys = [
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
    ];

    public function mount(): void
    {
        $values = SiteSetting::whereIn('setting_key', $this->keys)
            ->pluck('setting_value', 'setting_key')
            ->all();

        $this->form->fill([
            'mail_mailer' => $values['mail_mailer'] ?? 'smtp',
            'mail_host' => $values['mail_host'] ?? '',
            'mail_port' => $values['mail_port'] ?? '587',
            'mail_username' => $values['mail_username'] ?? '',
            'mail_password' => $values['mail_password'] ?? '',
            'mail_encryption' => $values['mail_encryption'] ?? 'tls',
            'mail_from_address' => $values['mail_from_address'] ?? '',
            'mail_from_name' => $values['mail_from_name'] ?? '',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('SMTP')
                    ->columns(2)
                    ->schema([
                        TextInput::make('mail_mailer')->label('Mailer')->default('smtp'),
                        TextInput::make('mail_host')->label('Host')->required(),
                        TextInput::make('mail_port')->label('Port')->numeric()->required(),
                        Select::make('mail_encryption')
                            ->label('Encryption')
                            ->options(['tls' => 'tls', 'ssl' => 'ssl'])
                            ->default('tls'),
                        TextInput::make('mail_username')->label('Username'),
                        TextInput::make('mail_password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->helperText('Leave blank to keep the current password.')
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('mail_from_address')->label('From address')->email()->required(),
                        TextInput::make('mail_from_name')->label('From name')->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            // Skip an empty password so we don't wipe the stored one.
            if ($key === 'mail_password' && blank($value)) {
                continue;
            }

            SiteSetting::updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $value],
            );
        }

        Notification::make()->title('Mail settings saved.')->success()->send();
    }
}
