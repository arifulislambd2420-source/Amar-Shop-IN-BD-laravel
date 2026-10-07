<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminArea;
use App\Models\SiteSetting;
use App\Support\SiteSettingsHelper;
use BackedEnum;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Edit the public info pages (About, Delivery, Returns, Privacy, Terms) as
 * rich text. Saved as `page_<key>` in site_settings. A page left empty keeps
 * showing its built-in text, so nothing disappears until an admin writes
 * something. Output is sanitized on render (App\Support\Html).
 */
class PolicyPages extends Page implements HasSchemas
{
    use HasAdminArea;
    use InteractsWithSchemas;

    /** page key => tab label */
    public const PAGES = [
        'about' => 'আমাদের সম্পর্কে',
        'delivery' => 'ডেলিভারি',
        'returns' => 'রিটার্ন ও রিফান্ড',
        'privacy' => 'প্রাইভেসি',
        'terms' => 'শর্তাবলী',
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Info Pages';

    protected static ?string $title = 'Info Pages (About, Delivery, Returns…)';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function settingKey(string $page): string
    {
        return 'page_'.$page;
    }

    public function mount(): void
    {
        $state = [];

        foreach (array_keys(self::PAGES) as $page) {
            $state[$page] = SiteSetting::where('setting_key', self::settingKey($page))->value('setting_value') ?? '';
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('pages')
                    ->tabs(array_map(
                        fn (string $key, string $label): Tab => Tab::make($label)->schema([
                            \App\Filament\Support\ImageUpload::configureRichEditor(RichEditor::make($key))
                                ->label($label)
                                ->helperText('খালি রাখলে সাইটের বর্তমান (বিল্ট-ইন) লেখা দেখানো হবে। শিরোনাম পেজে নিজে থেকেই থাকে।')
                                ->columnSpanFull(),
                        ]),
                        array_keys(self::PAGES),
                        array_values(self::PAGES),
                    )),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (array_keys(self::PAGES) as $page) {
            $key = self::settingKey($page);
            $html = trim((string) ($data[$page] ?? ''));

            // A visually empty editor ("<p></p>") counts as empty.
            if (trim(strip_tags($html, '<img>')) === '') {
                $html = '';
            }

            SiteSetting::updateOrCreate(['setting_key' => $key], ['setting_value' => $html]);
            SiteSettingsHelper::forget($key);
        }

        Notification::make()->title('পেজগুলো সেভ হয়েছে।')->success()->send();
    }
}
