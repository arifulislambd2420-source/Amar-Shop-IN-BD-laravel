<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Support\ImageUpload;
use App\Filament\Support\SettingsPage;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

/** Site settings — Header & Footer: the footer's logo, description and copyright line (the header logo is under General). */
class HeaderFooterSettings extends SettingsPage
{
    protected static ?string $slug = 'site-footer';

    protected static ?int $navigationSort = 4;

    protected static function label(): string
    {
        return 'হেডার ও ফুটার';
    }

    protected function keys(): array
    {
        return ['footer_logo', 'footer_description', 'footer_copyright'];
    }

    protected function sections(): array
    {
        return [
            Section::make(__('হেডার'))
                ->description(__('হেডারের লোগো ও দোকানের নাম "সাইট সেটিং" (সাধারণ) পেজে বদলান; ফোন ও হোয়াটসঅ্যাপ "যোগাযোগ ও সোশ্যাল" পেজে।')),
            Section::make(__('ফুটার'))
                ->description(__('খালি রাখলে বিল্ট-ইন লেখাই থাকবে।'))
                ->schema([
                    ImageUpload::make('footer_logo')
                        ->label(__('ফুটারের লোগো'))
                        ->helperText(__('ফুটারের পটভূমি গাঢ়, তাই এখানে সাদা/হালকা রঙের লোগো দিন। খালি থাকলে দোকানের নাম লেখা দেখাবে।')),
                    Textarea::make('footer_description')
                        ->label(__('ফুটারের বিবরণ (দোকানের পরিচিতি)'))
                        ->rows(3)
                        ->maxLength(500),
                    TextInput::make('footer_copyright')
                        ->label(__('কপিরাইট লাইন'))
                        ->placeholder(__('© ২০২৬ আমারশপ — সব অধিকার সংরক্ষিত'))
                        ->helperText(__('খালি থাকলে দোকানের নাম দিয়ে নিজে থেকে তৈরি হবে।'))
                        ->maxLength(300),
                ]),
        ];
    }
}
