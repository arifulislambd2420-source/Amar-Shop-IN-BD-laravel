<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Support\SettingsPage;
use App\Support\SiteSettingsHelper;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

/** Site settings — Contact & Social: phone, WhatsApp, e-mail, address, opening hours and social links. */
class ContactSocialSettings extends SettingsPage
{
    protected static ?string $slug = 'site-contact';

    protected static ?int $navigationSort = 5;

    protected static function label(): string
    {
        return 'যোগাযোগ ও সোশ্যাল';
    }

    protected function keys(): array
    {
        return [
            'contact_phone', 'contact_whatsapp', 'contact_email', 'contact_address', 'contact_hours',
            ...array_map(fn (string $network) => "social_{$network}", array_keys(SiteSettingsHelper::SOCIALS)),
        ];
    }

    protected function sections(): array
    {
        return [
            Section::make(__('যোগাযোগের তথ্য'))
                ->description(__('হেডার, ফুটার, আমাদের সম্পর্কে পেজ আর ইনভয়েসে দেখায়। খালি রাখলে ডিফল্ট তথ্য থাকবে।'))
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('contact_phone')
                        ->label(__('ফোন'))
                        ->placeholder('+880 1874-783819')
                        ->maxLength(30),
                    TextInput::make('contact_whatsapp')
                        ->label(__('হোয়াটসঅ্যাপ নম্বর'))
                        ->placeholder('8801874783819')
                        ->helperText(__('দেশের কোডসহ, যেমন 8801XXXXXXXXX। খালি রাখলে হোয়াটসঅ্যাপ বাটন দেখাবে না।'))
                        ->maxLength(30),
                    TextInput::make('contact_email')
                        ->label(__('ইমেইল'))
                        ->email()
                        ->maxLength(255),
                    TextInput::make('contact_address')
                        ->label(__('ঠিকানা'))
                        ->maxLength(500),
                    TextInput::make('contact_hours')
                        ->label(__('খোলার সময়'))
                        ->placeholder(__('শনি–বৃহস্পতি, সকাল ১০টা – রাত ৯টা'))
                        ->helperText(__('ফুটারে দেখায়। খালি রাখলে দেখাবে না।'))
                        ->maxLength(200)
                        ->columnSpanFull(),
                ]),
            Section::make(__('সোশ্যাল লিংক'))
                ->description(__('যে ঘর খালি থাকবে, ফুটারে তার লিংক দেখাবে না।'))
                ->columns(['default' => 1, 'md' => 2])
                ->schema(collect(SiteSettingsHelper::SOCIALS)
                    ->map(fn (string $label, string $network) => TextInput::make("social_{$network}")
                        ->label($label)
                        ->url()
                        ->placeholder('https://')
                        ->maxLength(500))
                    ->values()
                    ->all()),
        ];
    }
}
