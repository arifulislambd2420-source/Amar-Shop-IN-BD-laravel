<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Support\SettingsPage;
use App\Models\SiteSetting;
use App\Support\SiteSettingsHelper;
use App\Support\StorefrontCache;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;

/** Site settings — Colours (the admin panel's main colour follows the brand colour). */
class ColorSettings extends SettingsPage
{
    protected static ?string $slug = 'site-colors';

    protected static ?int $navigationSort = 2;

    protected static function label(): string
    {
        return 'রং';
    }

    protected function keys(): array
    {
        return array_map(fn (string $name) => "color_{$name}", array_keys(SiteSettingsHelper::COLOR_VARS));
    }

    /** Colors always show the color currently in use (the default when unset). */
    protected function fillState(array $state): array
    {
        foreach (array_keys(SiteSettingsHelper::COLOR_VARS) as $name) {
            $state["color_{$name}"] = SiteSettingsHelper::color($name);
        }

        return $state;
    }

    /** A color equal to its default is stored empty, so the site keeps following the default if it ever changes. */
    protected function valueToStore(string $key, mixed $value): string
    {
        $name = substr($key, 6);

        return strtolower((string) $value) === strtolower(SiteSettingsHelper::colorDefaults()[$name] ?? '') ? '' : (string) $value;
    }

    protected function sections(): array
    {
        return [
            Section::make(__('রং'))
                ->description(__('সাইটের রং। ডিফল্ট রং বদলাতে না চাইলে অপরিবর্তিত রাখুন। অ্যাডমিন প্যানেলের মূল রংও ব্র্যান্ড রং থেকে আসে।'))
                ->headerActions([
                    Action::make('resetColors')
                        ->label(__('ডিফল্টে ফেরত'))
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading(__('রং ডিফল্টে ফেরত নেবেন?'))
                        ->modalDescription(__('সব রং আগের ডিফল্ট রঙে ফিরে যাবে।'))
                        ->modalSubmitActionLabel(__('হ্যাঁ, ফেরত নিন'))
                        ->modalCancelActionLabel(__('বাতিল'))
                        ->action(fn () => $this->resetColors()),
                ])
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    ColorPicker::make('color_brand')->label(__('প্রাইমারি / ব্র্যান্ড (বাটন, লিংক, হাইলাইট)')),
                    ColorPicker::make('color_secondary')->label(__('সেকেন্ডারি (ফুটার, গাঢ় অংশ)')),
                    ColorPicker::make('color_accent')->label(__('অ্যাকসেন্ট (রেটিং তারা ইত্যাদি)')),
                    ColorPicker::make('color_background')->label(__('ব্যাকগ্রাউন্ড')),
                    ColorPicker::make('color_text')->label(__('লেখা')),
                    ColorPicker::make('color_success')->label(__('সফল')),
                    ColorPicker::make('color_error')->label(__('ত্রুটি')),
                ]),
        ];
    }

    /** Drop every saved color and show the defaults in the form. */
    public function resetColors(): void
    {
        foreach (SiteSettingsHelper::colorDefaults() as $name => $default) {
            SiteSetting::where('setting_key', "color_{$name}")->delete();
            SiteSettingsHelper::forget("color_{$name}");
            $this->data["color_{$name}"] = $default;
        }

        StorefrontCache::flush();

        Notification::make()->title(__('রং ডিফল্টে ফিরেছে।'))->success()->send();
    }
}
