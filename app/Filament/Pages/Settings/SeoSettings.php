<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Support\ImageUpload;
use App\Filament\Support\SettingsPage;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

/** Site settings — SEO: the default title / description / share picture for pages that give none of their own. */
class SeoSettings extends SettingsPage
{
    protected static ?string $slug = 'site-seo';

    protected static ?int $navigationSort = 7;

    protected static function label(): string
    {
        return 'এসইও (সার্চ ইঞ্জিন)';
    }

    protected function keys(): array
    {
        return ['seo_title', 'seo_description', 'og_image'];
    }

    protected function sections(): array
    {
        return [
            Section::make(__('এসইও (সার্চ ইঞ্জিন)'))
                ->description(__('যে পেজ নিজের টাইটেল/বিবরণ দেয়নি তার ডিফল্ট। খালি রাখলে বিল্ট-ইন লেখাই থাকবে।'))
                ->schema([
                    TextInput::make('seo_title')
                        ->label(__('এসইও টাইটেল'))
                        ->maxLength(70),
                    Textarea::make('seo_description')
                        ->label(__('মেটা বিবরণ'))
                        ->rows(3)
                        ->maxLength(300),
                    ImageUpload::make('og_image')
                        ->label(__('শেয়ারের ছবি (ফেসবুক ইত্যাদিতে)'))
                        ->helperText(__('১২০০×৬৩০ আকারের ছবি ভালো।')),
                ]),
        ];
    }
}
