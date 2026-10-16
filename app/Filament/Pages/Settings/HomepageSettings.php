<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Support\SettingsPage;
use App\Models\Category;
use App\Support\HomeSections;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/** Site settings — Homepage: which sections show and in what order, and the fallback hero / side-card texts. */
class HomepageSettings extends SettingsPage
{
    protected static ?string $slug = 'site-homepage';

    protected static ?int $navigationSort = 3;

    protected static function label(): string
    {
        return 'হোমপেজ';
    }

    protected function keys(): array
    {
        return [
            'home_sections', 'home_showcase_categories',
            'hero_title', 'hero_subtitle',
            'side_card_1_title', 'side_card_1_text', 'side_card_2_title', 'side_card_2_text',
        ];
    }

    /** The sections list always shows every section, in the saved order. */
    protected function fillState(array $state): array
    {
        $state['home_sections'] = HomeSections::all();
        $state['home_showcase_categories'] = HomeSections::showcaseCategoryIds();

        return $state;
    }

    /** JSON for a list setting; '' (= default behaviour) when there is nothing to store. */
    protected function valueToStore(string $key, mixed $value): string
    {
        if (! in_array($key, self::JSON_KEYS, true)) {
            return (string) $value;
        }

        $value = is_array($value) ? array_values($value) : [];

        if ($key === 'home_sections') {
            $value = array_values(array_filter(array_map(
                fn ($row) => is_array($row) && isset(HomeSections::SECTIONS[$row['key'] ?? ''])
                    ? ['key' => $row['key'], 'on' => (bool) ($row['on'] ?? false)]
                    : null,
                $value,
            )));
        } else {
            $value = array_values(array_unique(array_map('intval', $value)));
        }

        return $value === [] ? '' : (string) json_encode($value);
    }

    protected function sections(): array
    {
        return [
            Section::make(__('হোমপেজের সেকশন'))
                ->description(__('কোন সেকশন হোমপেজে দেখাবে আর কোনটার পর কোনটা — সুইচ দিয়ে চালু/বন্ধ করুন, তীর দিয়ে ওঠানামা করান।'))
                ->schema([
                    Repeater::make('home_sections')
                        ->hiddenLabel()
                        ->addable(false)
                        ->deletable(false)
                        ->reorderableWithButtons()
                        ->schema([
                            Hidden::make('key'),
                            Hidden::make('label'),
                            Toggle::make('on')
                                ->label(fn (Get $get): string => __((string) $get('label')))
                                ->default(true),
                        ]),
                    Select::make('home_showcase_categories')
                        ->label(__('"ক্যাটাগরি অনুযায়ী পণ্য" সেকশনে কোন ক্যাটাগরি দেখাবে'))
                        ->multiple()
                        ->options(fn () => Category::orderBy('name')->pluck('name', 'id')->all())
                        ->helperText(__('যে ক্রমে বাছবেন সেই ক্রমে দেখাবে। কিছু না বাছলে পণ্য আছে এমন প্রথম দুটি ক্যাটাগরি দেখাবে।')),
                ]),
            Section::make(__('হোমপেজের লেখা'))
                ->description(__('হিরো ব্যানার বা সাইড ব্যানার না থাকলে হোমপেজে এই লেখাগুলো দেখায়। খালি রাখলে বিল্ট-ইন লেখাই থাকবে।'))
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('hero_title')->label(__('হিরো শিরোনাম'))->maxLength(200),
                    TextInput::make('hero_subtitle')->label(__('হিরো উপশিরোনাম'))->maxLength(300),
                    TextInput::make('side_card_1_title')->label(__('সাইড কার্ড ১ — শিরোনাম'))->maxLength(120),
                    TextInput::make('side_card_1_text')->label(__('সাইড কার্ড ১ — লেখা'))->maxLength(200),
                    TextInput::make('side_card_2_title')->label(__('সাইড কার্ড ২ — শিরোনাম'))->maxLength(120),
                    TextInput::make('side_card_2_text')->label(__('সাইড কার্ড ২ — লেখা'))->maxLength(200),
                ]),
        ];
    }
}
