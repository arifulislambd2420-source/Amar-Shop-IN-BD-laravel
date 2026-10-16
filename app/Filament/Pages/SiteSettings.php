<?php

namespace App\Filament\Pages;

use App\Filament\Support\ImageUpload;
use App\Filament\Support\SettingsPage;
use App\Support\SiteSettingsHelper;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * সাইট সেটিং — General: the admin panel's language and the shop's identity (name, logo,
 * favicon). The rest of the site settings live on their own screens under this one
 * (see App\Filament\Support\SettingsPage and App\Filament\Pages\Settings\*).
 */
class SiteSettings extends SettingsPage
{
    protected static ?string $slug = 'site-settings';

    protected static ?int $navigationSort = 1;

    protected static function label(): string
    {
        return 'সাইট সেটিং';
    }

    public function getTitle(): string|Htmlable
    {
        return __('সাইট সেটিং — সাধারণ');
    }

    protected static function icon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedCog6Tooth;
    }

    protected function keys(): array
    {
        return ['admin_locale', 'site_name', 'site_name_en', 'site_logo', 'site_favicon'];
    }

    protected function fillState(array $state): array
    {
        $state['admin_locale'] = SiteSettingsHelper::adminLocale();

        return $state;
    }

    protected function sections(): array
    {
        return [
            Section::make(__('অ্যাডমিন প্যানেলের ভাষা'))
                ->description(__('এই অ্যাডমিন প্যানেল কোন ভাষায় দেখাবে। সাইটের (গ্রাহকের) ভাষা এতে বদলায় না।'))
                ->schema([
                    Select::make('admin_locale')
                        ->label(__('ভাষা'))
                        ->options(SiteSettingsHelper::ADMIN_LOCALES)
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->required(),
                ]),
            Section::make(__('সাইটের পরিচয়'))
                ->description(__('দোকানের নাম আর লোগো। লোগো না দিলে নামটাই লেখা আকারে দেখাবে। ফুটারের লোগো "হেডার ও ফুটার" পেজে।'))
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('site_name')
                        ->label(__('দোকানের নাম (বাংলা)'))
                        ->placeholder(__('আমারশপ'))
                        ->helperText(__('হেডার ও ফুটারে লোগোর বদলে দেখায় (লোগো না থাকলে)।')),
                    TextInput::make('site_name_en')
                        ->label(__('দোকানের নাম (ইংরেজি)'))
                        ->placeholder('Amar Shop in BD')
                        ->helperText(__('ব্রাউজারের টাইটেল ও ফুটারের কপিরাইট লাইনে ব্যবহার হয়।')),
                    ImageUpload::make('site_logo')
                        ->label(__('সাইটের লোগো (হেডার)'))
                        ->helperText(__('সাদা/হালকা পটভূমিতে মানানসই লোগো। ছবি: JPG, PNG বা WEBP।')),
                    ImageUpload::make('site_favicon')
                        ->label(__('ফেভিকন (ব্রাউজার ট্যাবের ছোট আইকন)'))
                        ->helperText(__('খালি থাকলে হেডারের লোগো ব্যবহার হবে। বর্গাকার ছবি ভালো।')),
                ]),
        ];
    }

    protected function afterSave(array $data): void
    {
        // The admin language applies from the next page load, so reload now if it changed.
        $switched = ($data['admin_locale'] ?? null) !== app()->getLocale();
        app()->setLocale(SiteSettingsHelper::adminLocale());

        if ($switched) {
            $this->redirect(static::getUrl());
        }
    }
}
