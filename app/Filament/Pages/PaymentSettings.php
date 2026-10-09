<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminArea;
use App\Models\SiteSetting;
use App\Support\AdminAccess;
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
use Illuminate\Support\Facades\Crypt;
use UnitEnum;

/**
 * bKash Tokenized Checkout credentials — same encrypted-secret pattern as
 * CourierSettings/steadfast_secret_key: app_secret and password are never
 * echoed back into the form (mount() always leaves them blank), and are
 * Crypt::encryptString()'d on save(). Leaving either blank on save keeps
 * the current value; App\Services\Payment\BkashService decrypts them (with
 * a plaintext fallback) when actually calling the bKash API.
 */
class PaymentSettings extends Page implements HasSchemas
{
    use HasAdminArea;

    protected static string $adminArea = AdminAccess::AREA_SETTINGS;

    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'পেমেন্ট (বিকাশ)';

    protected static ?string $title = 'পেমেন্ট সেটিং — বিকাশ';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Secret keys that are encrypted at rest and never re-echoed into the form. */
    protected array $secretKeys = ['bkash_app_secret', 'bkash_password'];

    /** All keys managed by this page. */
    protected array $keys = ['bkash_app_key', 'bkash_app_secret', 'bkash_username', 'bkash_password', 'bkash_base_url'];

    public function mount(): void
    {
        $values = SiteSetting::whereIn('setting_key', $this->keys)
            ->pluck('setting_value', 'setting_key')
            ->all();

        $this->form->fill([
            'bkash_app_key' => $values['bkash_app_key'] ?? '',
            'bkash_app_secret' => '',
            'bkash_username' => $values['bkash_username'] ?? '',
            'bkash_password' => '',
            'bkash_base_url' => $values['bkash_base_url'] ?? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('বিকাশ টোকেনাইজড চেকআউট')
                    ->description('অ্যাপ সিক্রেট বা পাসওয়ার্ড খালি রাখলে বর্তমান মানই থাকবে।')
                    ->columns(2)
                    ->schema([
                        Select::make('bkash_base_url')
                            ->label('পরিবেশ')
                            ->columnSpanFull()
                            ->required()
                            ->options([
                                'https://tokenized.sandbox.bka.sh/v1.2.0-beta' => 'স্যান্ডবক্স (পরীক্ষামূলক)',
                                'https://tokenized.pay.bka.sh/v1.2.0-beta' => 'লাইভ (আসল)',
                            ]),
                        TextInput::make('bkash_app_key')
                            ->label('অ্যাপ কী')
                            ->placeholder('আপনার বিকাশ অ্যাপ কী লিখুন'),
                        TextInput::make('bkash_app_secret')
                            ->label('অ্যাপ সিক্রেট')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('bkash_username')
                            ->label('ইউজারনেম'),
                        TextInput::make('bkash_password')
                            ->label('পাসওয়ার্ড')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if (in_array($key, $this->secretKeys, true)) {
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

        Notification::make()->title('পেমেন্ট সেটিং সেভ হয়েছে।')->success()->send();
    }
}
