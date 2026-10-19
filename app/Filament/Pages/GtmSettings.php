<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminArea;
use App\Models\SiteSetting;
use App\Support\AdminAccess;
use App\Support\Tracking;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * GTM / Pixel settings — stores the gtm_id key in site_settings.
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
        ]);
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
                            ->helperText(__('চালু থাকলে সারা সাইটে (ল্যান্ডিং পেজসহ) PageView, ViewContent, AddToCart, InitiateCheckout, Purchase, Search, ViewCategory, CompleteRegistration আর আগ্রহের ইভেন্ট (সময় ১০/৩০/৬০/১২০ সেকেন্ড, স্ক্রল ২৫/৫০/৭৫/১০০%) যায়। খালি = বন্ধ। বট ও সার্চ ইঞ্জিনের ক্রলার থেকে কিছুই যায় না।')),
                    ]),
                Section::make(__('অর্ডার দেওয়ার ইভেন্ট'))
                    ->description(__('কাস্টমার অর্ডার করার মুহূর্তে ব্রাউজার থেকে এই নামে একটি ইভেন্ট যায় (GTM ও Meta Pixel দুটোতেই), Purchase ইভেন্টের পাশাপাশি। অর্ডারের টাকা ও পণ্যের তথ্য এতে থাকে।'))
                    ->schema([
                        TextInput::make('tracking_order_event')
                            ->label(__('ইভেন্টের নাম'))
                            ->placeholder(Tracking::DEFAULT_ORDER_EVENT)
                            ->maxLength(40)
                            ->helperText(__('ইংরেজি অক্ষর, সংখ্যা ও আন্ডারস্কোর (_), অক্ষর দিয়ে শুরু। খালি রাখলে OrderPlaced।'))
                            ->rule(fn () => function (string $attribute, $value, \Closure $fail): void {
                                if (filled($value) && ! preg_match(Tracking::EVENT_NAME_PATTERN, (string) $value)) {
                                    $fail(__('নামে শুধু ইংরেজি অক্ষর, সংখ্যা ও _ চলবে, অক্ষর দিয়ে শুরু করতে হবে।'));
                                }
                            }),
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

        $name = trim((string) ($data['tracking_order_event'] ?? ''));
        SiteSetting::updateOrCreate(
            ['setting_key' => 'tracking_order_event'],
            ['setting_value' => $name === Tracking::DEFAULT_ORDER_EVENT ? '' : $name],
        );

        Notification::make()->title(__('জিটিএম / পিক্সেল সেটিং সেভ হয়েছে।'))->success()->send();
    }
}
