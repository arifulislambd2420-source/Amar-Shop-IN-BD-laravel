<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplates;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
 * Customer SMS credentials — same encrypted-secret pattern as
 * CourierSettings/PaymentSettings: the API key is never echoed back into
 * the form (mount() always leaves it blank), blank on save keeps the
 * current value, a new value is Crypt::encryptString()'d.
 */
class SmsSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'SMS';

    protected static ?string $title = 'SMS Settings';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public ?array $data = [];

    protected array $keys = ['sms_enabled', 'sms_api_key', 'sms_sender_id', 'sms_language'];

    public function mount(): void
    {
        $values = SiteSetting::whereIn('setting_key', $this->keys)
            ->pluck('setting_value', 'setting_key')
            ->all();

        $this->form->fill([
            // No DB row yet -> fall back to what SMS_ENABLED says.
            'sms_enabled' => isset($values['sms_enabled'])
                ? $values['sms_enabled'] === '1'
                : (bool) config('services.sms.enabled'),
            'sms_api_key' => '',
            'sms_sender_id' => $values['sms_sender_id'] ?? '',
            'sms_language' => $values['sms_language'] ?? 'bn',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testSms')
                ->label('Send test SMS')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->modalHeading('Send a test SMS')
                ->modalDescription('Uses the saved settings (save first if you just changed them). Shows the gateway\'s raw answer.')
                ->schema([
                    TextInput::make('number')
                        ->label('Your mobile number')
                        ->placeholder('01XXXXXXXXX')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $sms = app(SmsService::class);
                    $number = $sms->normalizeNumber($data['number']);

                    if (! $number) {
                        Notification::make()->title('Not a valid Bangladeshi mobile number.')->danger()->send();

                        return;
                    }

                    if (! $sms->configured()) {
                        Notification::make()->title('Save an API key and Sender ID first.')->danger()->send();

                        return;
                    }

                    $result = $sms->send($number, config('site.legal_name').': test SMS - it works!');

                    Notification::make()
                        ->title($result['ok'] ? 'Gateway accepted the test SMS' : 'Gateway did not accept the SMS')
                        ->body(mb_substr($result['response'], 0, 300))
                        ->color($result['ok'] ? 'success' : 'danger')
                        ->persistent()
                        ->send();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Customer SMS (BulkSMSBD)')
                    ->description('Sent once per order: when it is confirmed, shipped and delivered. Leave the API key blank to keep the current one.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('sms_enabled')
                            ->label('Send SMS')
                            ->helperText('Off by default — nothing is sent until you turn this on.')
                            ->columnSpanFull(),
                        TextInput::make('sms_api_key')
                            ->label('API key')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('sms_sender_id')
                            ->label('Sender ID')
                            ->placeholder('Your approved sender ID'),
                        Select::make('sms_language')
                            ->label('Message language')
                            ->options(SmsTemplates::LANGUAGES)
                            ->required()
                            ->helperText('Bangla SMS is billed per 70 characters, English per 160.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if ($key === 'sms_api_key') {
                if (blank($value)) {
                    // Blank means "keep the current value" — skip writing.
                    continue;
                }

                $value = Crypt::encryptString($value);
            }

            if ($key === 'sms_enabled') {
                $value = $value ? '1' : '0';
            }

            SiteSetting::updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $value ?? ''],
            );
        }

        Notification::make()->title('SMS settings saved.')->success()->send();
    }
}
