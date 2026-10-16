<?php

namespace App\Filament\Support;

use App\Filament\Concerns\HasAdminArea;
use App\Models\SiteSetting;
use App\Support\AdminAccess;
use App\Support\SiteSettingsHelper;
use App\Support\StorefrontCache;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * One screen of the site settings. The old single long "Site settings" page is split into
 * several of these (General, Colours, Homepage, Header & Footer, Contact & Social,
 * Delivery & Orders, SEO); each keeps the same site_settings keys it always had, shows
 * only its own fields and has its own Save button — saving one never touches the others.
 * They appear as sub-items under "Site settings" in the Settings menu.
 */
abstract class SettingsPage extends Page implements HasSchemas
{
    use HasAdminArea;
    use InteractsWithSchemas;

    protected static string $adminArea = AdminAccess::AREA_SETTINGS;

    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Settings stored as JSON (lists), handled separately from plain text values. */
    protected const JSON_KEYS = ['home_sections', 'home_showcase_categories'];

    /** The site_settings keys this screen manages. */
    abstract protected function keys(): array;

    /** The form sections. */
    abstract protected function sections(): array;

    /** Navigation label and page title (Bangla source text; translated for the admin). */
    abstract protected static function label(): string;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('সেটিংস');
    }

    public static function getNavigationParentItem(): ?string
    {
        // Every screen except the first one is listed under "Site settings".
        return static::class === \App\Filament\Pages\SiteSettings::class ? null : __('সাইট সেটিং');
    }

    public static function getNavigationLabel(): string
    {
        return __(static::label());
    }

    public function getTitle(): string|Htmlable
    {
        return __(static::label());
    }

    public function mount(): void
    {
        $values = SiteSetting::whereIn('setting_key', $this->keys())
            ->pluck('setting_value', 'setting_key')
            ->all();

        $state = collect($this->keys())->mapWithKeys(fn ($key) => [$key => $values[$key] ?? ''])->all();

        $this->form->fill($this->fillState($state));
    }

    /** Adjust what the form starts with (defaults shown for unset values, full lists…). */
    protected function fillState(array $state): array
    {
        return $state;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components($this->sections());
    }

    /** The text to store for a key ('' = "use the built-in default"). */
    protected function valueToStore(string $key, mixed $value): string
    {
        return (string) $value;
    }

    /** After saving: anything this screen needs to do (reload for a new language …). */
    protected function afterSave(array $data): void {}

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($this->keys() as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            SiteSetting::updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $this->valueToStore($key, $data[$key])],
            );

            SiteSettingsHelper::forget($key);
        }

        StorefrontCache::flush();

        Notification::make()->title(__('সেভ হয়েছে।'))->success()->send();

        $this->afterSave($data);
    }

    protected static function icon(): string|BackedEnum|null
    {
        return null;
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        // Sub-items sit under their parent and need no icon of their own.
        return static::getNavigationParentItem() === null ? static::icon() : null;
    }
}
