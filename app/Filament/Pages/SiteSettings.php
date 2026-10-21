<?php

namespace App\Filament\Pages;

use App\Filament\Support\ImageUpload;
use App\Filament\Support\SettingsPage;
use App\Models\SiteSetting;
use App\Support\AdminTheme;
use App\Support\SiteSettingsHelper;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Notifications\Notification;
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
        return ['admin_locale', 'site_name', 'site_name_en', 'site_logo', 'site_favicon', ...AdminTheme::KEYS];
    }

    protected function fillState(array $state): array
    {
        $state['admin_locale'] = SiteSettingsHelper::adminLocale();

        // The admin colours in use (the defaults when none is saved).
        $state['admin_color_primary'] = AdminTheme::primary();
        $state['admin_color_sidebar'] = AdminTheme::sidebar();

        return $state;
    }

    /** An admin colour equal to its default is stored empty, so it keeps following the default. */
    protected function valueToStore(string $key, mixed $value): string
    {
        if (in_array($key, AdminTheme::KEYS, true)) {
            return strtolower((string) $value) === strtolower(AdminTheme::defaults()[$key]) ? '' : (string) $value;
        }

        return (string) $value;
    }

    /** Put both admin colours back to the defaults (navy sidebar, blue main colour). */
    public function resetAdminColors(): void
    {
        foreach (AdminTheme::defaults() as $key => $default) {
            SiteSetting::where('setting_key', $key)->delete();
            SiteSettingsHelper::forget($key);
            $this->data[$key] = $default;
        }

        Notification::make()->title(__('অ্যাডমিনের রং ডিফল্টে ফিরেছে।'))->success()->send();

        $this->redirect(static::getUrl());
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
            Section::make(__('অ্যাডমিনের রং'))
                ->description(__('এই অ্যাডমিন প্যানেলের নিজস্ব রং — সাইটের (গ্রাহকের দেখা) ব্র্যান্ড রং থেকে আলাদা, একটা বদলালে অন্যটা বদলায় না।'))
                ->headerActions([
                    Action::make('resetAdminColors')
                        ->label(__('ডিফল্টে ফেরত'))
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading(__('অ্যাডমিনের রং ডিফল্টে ফেরত নেবেন?'))
                        ->modalSubmitActionLabel(__('হ্যাঁ, ফেরত নিন'))
                        ->action(fn () => $this->resetAdminColors()),
                ])
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    ColorPicker::make('admin_color_sidebar')
                        ->label(__('সাইডবারের রং'))
                        ->helperText(__('ডিফল্ট গাঢ় নেভি। হালকা রং দিলে সাইডবারের লেখা নিজে থেকেই গাঢ় হয়ে যায়।'))
                        ->required()
                        ->regex('/^#[0-9a-fA-F]{6}$/'),
                    ColorPicker::make('admin_color_primary')
                        ->label(__('অ্যাডমিনের মূল রং'))
                        ->helperText(__('সক্রিয় মেনু, বাটন ও লিংকের রং। ডিফল্ট কমলা।'))
                        ->required()
                        ->regex('/^#[0-9a-fA-F]{6}$/'),
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
        // The admin language and the admin colours apply from the next page load, so reload now.
        app()->setLocale(SiteSettingsHelper::adminLocale());

        $this->redirect(static::getUrl());
    }
}
