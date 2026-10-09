<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminArea;
use App\Models\SiteSetting;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplates;
use App\Support\AdminAccess;
use App\Support\SiteSettingsHelper;
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
    use HasAdminArea;

    protected static string $adminArea = AdminAccess::AREA_SETTINGS;

    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'এসএমএস';

    protected static ?string $title = 'এসএমএস সেটিং';

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
                ->label('টেস্ট এসএমএস পাঠান')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->modalHeading('একটি টেস্ট এসএমএস পাঠান')
                ->modalDescription('সেভ করা সেটিং দিয়ে পাঠানো হয় (এইমাত্র বদলালে আগে সেভ করুন)। গেটওয়ের আসল উত্তর দেখাবে।')
                ->schema([
                    TextInput::make('number')
                        ->label('আপনার মোবাইল নম্বর')
                        ->placeholder('01XXXXXXXXX')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $sms = app(SmsService::class);
                    $number = $sms->normalizeNumber($data['number']);

                    if (! $number) {
                        Notification::make()->title('সঠিক বাংলাদেশি মোবাইল নম্বর নয়।')->danger()->send();

                        return;
                    }

                    if (! $sms->configured()) {
                        Notification::make()->title('আগে এপিআই কী আর সেন্ডার আইডি সেভ করুন।')->danger()->send();

                        return;
                    }

                    $result = $sms->send($number, SiteSettingsHelper::siteNameEn().': test SMS - it works!');

                    Notification::make()
                        ->title($result['ok'] ? 'গেটওয়ে টেস্ট এসএমএস গ্রহণ করেছে' : 'গেটওয়ে এসএমএসটি গ্রহণ করেনি')
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
                Section::make('গ্রাহককে এসএমএস (BulkSMSBD)')
                    ->description('প্রতি অর্ডারে কনফার্ম, শিপ আর ডেলিভারির সময় একবার করে যায়। এপিআই কী খালি রাখলে বর্তমানটাই থাকবে।')
                    ->columns(2)
                    ->schema([
                        Toggle::make('sms_enabled')
                            ->label('এসএমএস পাঠান')
                            ->helperText('ডিফল্টে বন্ধ — চালু না করা পর্যন্ত কিছু পাঠানো হবে না।')
                            ->columnSpanFull(),
                        TextInput::make('sms_api_key')
                            ->label('এপিআই কী')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('sms_sender_id')
                            ->label('সেন্ডার আইডি')
                            ->placeholder('আপনার অনুমোদিত সেন্ডার আইডি'),
                        Select::make('sms_language')
                            ->label('বার্তার ভাষা')
                            ->options(SmsTemplates::LANGUAGES)
                            ->required()
                            ->helperText('বাংলা এসএমএস ৭০ অক্ষরে, ইংরেজি ১৬০ অক্ষরে একটি হিসেবে চার্জ হয়।'),
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

        Notification::make()->title('এসএমএস সেটিং সেভ হয়েছে।')->success()->send();
    }
}
