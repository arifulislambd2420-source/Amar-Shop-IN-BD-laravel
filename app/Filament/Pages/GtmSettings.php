<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\SiteSetting;
use App\Services\Tracking\ServerPurchase;
use App\Support\AdminAccess;
use App\Support\Tracking;
use App\Support\TrackingSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Tracking settings: GTM container, Meta Pixel, GA4, the "order placed" browser event, and the
 * server-side Purchase (Meta Conversions API + GA4 Measurement Protocol). The two secrets (Meta
 * access token, GA4 API secret) are stored encrypted and never shown again — only their last four
 * characters. See App\Support\TrackingSettings and App\Services\Tracking\ServerPurchase.
 */
class GtmSettings extends Page implements HasSchemas
{
    use HasAdminArea;

    protected static string $adminArea = AdminAccess::AREA_SETTINGS;

    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public static function getNavigationGroup(): string|UnitEnum|null
    {

        return __('সেটিংস');

    }

    public static function getNavigationLabel(): string
    {

        return __('জিটিএম / পিক্সেল');

    }

    public function getTitle(): string|Htmlable
    {

        return __('জিটিএম / পিক্সেল');

    }

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'gtm_id' => SiteSetting::where('setting_key', 'gtm_id')->value('setting_value') ?? '',
            'meta_pixel_id' => SiteSetting::where('setting_key', 'meta_pixel_id')->value('setting_value') ?? '',
            'tracking_order_event' => Tracking::orderEventName(),
            'capi_test_event_code' => TrackingSettings::capiTestCode() ?? '',
            'ga4_measurement_id' => TrackingSettings::ga4Id() ?? '',
            'tracking_purchase_status' => TrackingSettings::purchaseStatus(),
            // The secrets are never put back into the form.
            'capi_access_token' => null,
            'ga4_api_secret' => null,
        ]);
    }

    /** Status choices for the server-side Purchase, in the order an order moves through them. */
    private function statusOptions(): array
    {
        $labels = OrderResource::statuses();

        return collect(TrackingSettings::STATUS_ORDER)->mapWithKeys(fn (string $s) => [$s => $labels[$s] ?? $s])->all();
    }

    private function secretField(string $name, string $label, string $settingKey, string $savedHint, string $emptyHint): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->password()
            ->autocomplete('off')
            ->maxLength(1000)
            ->placeholder(fn (): string => TrackingSettings::masked($settingKey) ?? '')
            ->helperText(fn (): string => TrackingSettings::masked($settingKey)
                ? __($savedHint, ['value' => TrackingSettings::masked($settingKey)])
                : __($emptyHint))
            ->rule(fn () => function (string $attribute, $value, \Closure $fail): void {
                if (filled($value) && preg_match('/\s/', (string) $value)) {
                    $fail(__('এতে ফাঁকা জায়গা থাকতে পারবে না।'));
                }
            });
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('গুগল ট্যাগ ম্যানেজার / মেটা পিক্সেল'))
                    ->schema([
                        TextInput::make('gtm_id')
                            ->label(__('জিটিএম কনটেইনার আইডি'))
                            ->placeholder('GTM-XXXXXXX')
                            ->live(onBlur: true)
                            ->helperText(__('GTM- দিয়ে শুরু, তারপর ইংরেজি অক্ষর/সংখ্যা (যেমন GTM-ABC1234)। ভুল ফরম্যাটে সেভ হবে না; খালি = বন্ধ।'))
                            ->dehydrateStateUsing(fn (?string $state): string => strtoupper(trim((string) $state)))
                            ->rule(fn () => function (string $attribute, $value, \Closure $fail): void {
                                $value = strtoupper(trim((string) $value));

                                if ($value !== '' && ! preg_match(Tracking::GTM_ID_PATTERN, $value)) {
                                    $fail(__('জিটিএম আইডি ঠিক নয়। এটি GTM-ABC1234 এর মতো হতে হবে (GTM- আর তার পর ৪–১০টি অক্ষর/সংখ্যা)।'));
                                }
                            }),
                        TextInput::make('meta_pixel_id')
                            ->label(__('মেটা (ফেসবুক) পিক্সেল আইডি'))
                            ->placeholder('1234567890123456')
                            ->helperText(__('চালু থাকলে সারা সাইটে (ল্যান্ডিং পেজসহ) PageView, ViewContent, AddToCart, InitiateCheckout, Search, ViewCategory, CompleteRegistration, আর আগ্রহের ইভেন্ট (সময় ১০/৩০/৬০/১২০ সেকেন্ড, স্ক্রল ২৫/৫০/৭৫/১০০%) যায়। Purchase যায় সার্ভার থেকে (নিচে)। খালি = বন্ধ। বট ও সার্চ ইঞ্জিনের ক্রলার থেকে কিছুই যায় না।')),
                    ]),
                Section::make(__('অর্ডার দেওয়ার ইভেন্ট'))
                    ->description(__('কাস্টমার অর্ডার করার মুহূর্তে ব্রাউজার থেকে এই নামে একটি ইভেন্ট যায় (GTM ও Meta Pixel দুটোতেই)। অর্ডারের টাকা ও পণ্যের তথ্য এতে থাকে। ব্রাউজার থেকে আর Purchase যায় না — Purchase যায় অর্ডার কনফার্ম হলে সার্ভার থেকে।'))
                    ->schema([
                        TextInput::make('tracking_order_event')
                            ->label(__('ইভেন্টের নাম'))
                            ->placeholder(Tracking::DEFAULT_ORDER_EVENT)
                            ->maxLength(40)
                            ->helperText(__('ইংরেজি অক্ষর, সংখ্যা ও আন্ডারস্কোর (_), অক্ষর দিয়ে শুরু। খালি রাখলে OrderPlaced।'))
                            ->rule(fn () => function (string $attribute, $value, \Closure $fail): void {
                                if (filled($value) && ! preg_match(Tracking::EVENT_NAME_PATTERN, (string) $value)) {
                                    $fail(__('নামে শুধু ইংরেজি অক্ষর, সংখ্যা ও _ চলবে, অক্ষর দিয়ে শুরু করতে হবে।'));
                                } elseif (strtolower((string) $value) === 'purchase') {
                                    // The real Purchase comes from the server; the same name here would count every sale twice.
                                    $fail(__('"Purchase" নামটি নেওয়া যাবে না — Purchase সার্ভার থেকে আলাদা করে যায়, একই নাম দিলে দ্বিগুণ গোনা হবে।'));
                                }
                            }),
                    ]),
                Section::make(__('গুগল অ্যানালিটিক্স ৪ (GA4)'))
                    ->key('ga4')
                    ->description(__('Measurement ID দিলে — আর GTM না থাকলে — GA4 সরাসরি সাইটে লোড হয়। API secret সার্ভার থেকে Purchase পাঠানোর জন্য (Measurement Protocol)।'))
                    ->schema([
                        TextInput::make('ga4_measurement_id')
                            ->label(__('GA4 Measurement ID'))
                            ->placeholder('G-XXXXXXXXXX')
                            ->live(onBlur: true)
                            ->dehydrateStateUsing(fn (?string $state): string => strtoupper(trim((string) $state)))
                            ->helperText(__('G- দিয়ে শুরু (যেমন G-ABC123XYZ9)। ভুল ফরম্যাটে সেভ হবে না; খালি = বন্ধ।'))
                            ->rule(fn () => function (string $attribute, $value, \Closure $fail): void {
                                $value = strtoupper(trim((string) $value));

                                if ($value !== '' && ! preg_match(TrackingSettings::GA4_ID_PATTERN, $value)) {
                                    $fail(__('GA4 আইডি ঠিক নয়। এটি G-ABC123XYZ9 এর মতো হতে হবে।'));
                                }
                            }),
                        Text::make(__('⚠ GTM চালু আছে, তাই GA4 সরাসরি লোড হবে না (দ্বিগুণ গোনা এড়াতে)। ব্রাউজারের GA4 ইভেন্ট GTM কনটেইনারের GA4 ট্যাগ থেকে যাবে — সেটি সেখানে বসান। এখানের Measurement ID আর API secret শুধু সার্ভার থেকে Purchase পাঠাতে লাগে। একই GA4 ট্যাগ GTM-এ আর এখানে দুই জায়গায় রাখবেন না।'))
                            ->color('warning')
                            ->visible(fn (Get $get): bool => filled(trim((string) $get('gtm_id'))) && filled(trim((string) $get('ga4_measurement_id')))),
                        $this->secretField('ga4_api_secret', __('GA4 API secret'), TrackingSettings::GA4_SECRET,
                            'সেভ করা আছে (:value)। বদলাতে চাইলে নতুনটি লিখুন; খালি রাখলে আগেরটাই থাকবে।',
                            'GA4 → Admin → Data streams → Measurement Protocol API secrets থেকে নিন। সেভের পর আর দেখা যাবে না।'),
                        Toggle::make('ga4_api_secret_clear')
                            ->label(__('সেভ করা GA4 API secret মুছে ফেলুন'))
                            ->dehydrated(false)
                            ->visible(fn (): bool => TrackingSettings::masked(TrackingSettings::GA4_SECRET) !== null),
                    ])
                    ->headerActions([$this->testGa4Action()]),
                Section::make(__('সার্ভার থেকে Purchase (Meta Conversions API ও GA4)'))
                    ->key('server')
                    ->description(__('অর্ডার নিচের স্ট্যাটাসে (বা তার পরের কোনো স্ট্যাটাসে) পৌঁছালে সার্ভার Meta ও GA4-কে Purchase জানায় — প্রতি অর্ডারে একবার, কিউ দিয়ে, ব্যর্থ হলে কয়েকবার আবার চেষ্টা করে। ফোন, নাম ও শহর hash করে পাঠানো হয়। ফল দেখা যায় অর্ডারের পাতায়।'))
                    ->schema([
                        Select::make('tracking_purchase_status')
                            ->label(__('কোন স্ট্যাটাসে Purchase যাবে'))
                            ->options(fn (): array => $this->statusOptions())
                            ->native(false)
                            ->selectablePlaceholder(false)
                            ->required()
                            ->helperText(__('ডিফল্ট: কনফার্মড। বাতিল ও যাচাই-চলা অর্ডারে কখনো যায় না।')),
                        $this->secretField('capi_access_token', __('Meta Conversions API access token'), TrackingSettings::CAPI_TOKEN,
                            'সেভ করা আছে (:value)। বদলাতে চাইলে নতুনটি লিখুন; খালি রাখলে আগেরটাই থাকবে।',
                            'Events Manager → আপনার Pixel → Settings → Conversions API → Generate access token। সেভের পর আর দেখা যাবে না। (Pixel আইডি উপরে দেওয়া থাকতে হবে।)'),
                        Toggle::make('capi_access_token_clear')
                            ->label(__('সেভ করা Meta access token মুছে ফেলুন'))
                            ->dehydrated(false)
                            ->visible(fn (): bool => TrackingSettings::masked(TrackingSettings::CAPI_TOKEN) !== null),
                        TextInput::make('capi_test_event_code')
                            ->label(__('Test Event Code'))
                            ->placeholder('TEST12345')
                            ->maxLength(60)
                            ->helperText(__('Events Manager → Test events থেকে। এটি দেওয়া থাকলে সার্ভারের সব Purchase শুধু "Test events"-এ যায়, আসল হিসাবে নয় — পরীক্ষা শেষে মুছে দিন।')),
                    ])
                    ->headerActions([$this->testMetaAction()]),
            ]);
    }

    // ── Test connection ──────────────────────────────────────────────────

    /** What was typed now, else what is saved. */
    private function typed(string $field, ?string $saved): ?string
    {
        $value = trim((string) ($this->data[$field] ?? ''));

        return $value !== '' ? $value : $saved;
    }

    private function result(array $result): void
    {
        Notification::make()
            ->title($result['ok'] ? __('সংযোগ ঠিক আছে') : __('সংযোগ হয়নি'))
            ->body($result['message'])
            ->color($result['ok'] ? 'success' : 'danger')
            ->persistent()
            ->send();
    }

    private function testMetaAction(): Action
    {
        return Action::make('testMeta')
            ->label(__('Test connection (Meta)'))
            ->icon(Heroicon::OutlinedSignal)
            ->color('gray')
            ->action(function (): void {
                $pixel = preg_replace('/\D+/', '', (string) ($this->data['meta_pixel_id'] ?? ''));
                $token = $this->typed('capi_access_token', TrackingSettings::capiToken());

                if ($pixel === '' || $token === null) {
                    Notification::make()->title(__('Meta Pixel আইডি ও access token দিন'))->warning()->send();

                    return;
                }

                $this->result(app(ServerPurchase::class)->testMeta($pixel, $token, $this->typed('capi_test_event_code', TrackingSettings::capiTestCode())));
            });
    }

    private function testGa4Action(): Action
    {
        return Action::make('testGa4')
            ->label(__('Test connection (GA4)'))
            ->icon(Heroicon::OutlinedSignal)
            ->color('gray')
            ->action(function (): void {
                $id = strtoupper(trim((string) ($this->data['ga4_measurement_id'] ?? '')));
                $secret = $this->typed('ga4_api_secret', TrackingSettings::ga4Secret());

                if (! preg_match(TrackingSettings::GA4_ID_PATTERN, $id) || $secret === null) {
                    Notification::make()->title(__('GA4 Measurement ID ও API secret দিন'))->warning()->send();

                    return;
                }

                $this->result(app(ServerPurchase::class)->testGa4($id, $secret));
            });
    }

    // ── Save ─────────────────────────────────────────────────────────────

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

        $name = trim((string) ($data['tracking_order_event'] ?? ''));
        SiteSetting::updateOrCreate(
            ['setting_key' => 'tracking_order_event'],
            ['setting_value' => $name === Tracking::DEFAULT_ORDER_EVENT ? '' : $name],
        );

        foreach ([
            TrackingSettings::CAPI_TEST_CODE => trim((string) ($data['capi_test_event_code'] ?? '')),
            TrackingSettings::GA4_ID => strtoupper(trim((string) ($data['ga4_measurement_id'] ?? ''))),
            TrackingSettings::PURCHASE_STATUS => (string) ($data['tracking_purchase_status'] ?? TrackingSettings::DEFAULT_STATUS),
        ] as $key => $value) {
            SiteSetting::updateOrCreate(['setting_key' => $key], ['setting_value' => $key === TrackingSettings::PURCHASE_STATUS && $value === TrackingSettings::DEFAULT_STATUS ? '' : $value]);
        }

        // Secrets: a new value replaces the saved one, empty keeps it, the toggle removes it.
        foreach ([
            ['capi_access_token', TrackingSettings::CAPI_TOKEN, 'capi_access_token_clear'],
            ['ga4_api_secret', TrackingSettings::GA4_SECRET, 'ga4_api_secret_clear'],
        ] as [$field, $key, $clear]) {
            $typed = trim((string) ($data[$field] ?? ''));

            if (($this->data[$clear] ?? false) === true) {
                TrackingSettings::putSecret($key, '');
            } elseif ($typed !== '') {
                TrackingSettings::putSecret($key, $typed);
            }
        }

        // Nothing secret stays in the page after saving.
        $this->data['capi_access_token'] = null;
        $this->data['ga4_api_secret'] = null;
        $this->data['capi_access_token_clear'] = false;
        $this->data['ga4_api_secret_clear'] = false;

        Notification::make()->title(__('জিটিএম / পিক্সেল সেটিং সেভ হয়েছে।'))->success()->send();
    }
}
