<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Models\AdminUser;
use App\Models\SiteSetting;
use App\Support\SiteSettingsHelper;
use Filament\Forms\Components\TextInput;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/**
 * The admin panel is English by default; Site Setting → admin language switches it to Bangla.
 * Our own texts are Bangla source strings looked up with __(); lang/en.json holds the English.
 */
class AdminLanguageTest extends TestCase
{
    use CreatesShopData;

    private function needsIntl(): void
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }
    }

    private function admin(): AdminUser
    {
        $admin = AdminUser::create(['username' => 'boss', 'password' => 'secret-pass-1', 'role' => 'super_admin']);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_the_admin_is_in_english_by_default(): void
    {
        $this->needsIntl();
        $this->admin();

        $this->assertSame('en', SiteSettingsHelper::adminLocale());

        $this->get('/admin')->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('Site settings')
            ->assertSee('Dashboard')
            ->assertDontSee('সাইট সেটিং')
            ->assertDontSee('ড্যাশবোর্ড');

        $this->get('/admin/orders')->assertOk()->assertSee('Created at')->assertDontSee('তৈরির সময়');
        $this->get('/admin/products')->assertOk()->assertSee('Products')->assertDontSee('পণ্য');
    }

    public function test_choosing_bangla_in_site_setting_switches_the_whole_admin(): void
    {
        $this->needsIntl();
        $this->admin();
        $this->setting('admin_locale', 'bn');

        $this->get('/admin')->assertOk()->assertSee('lang="bn"', false)->assertSee('সাইট সেটিং')->assertDontSee('Site settings');
        $this->get('/admin/orders')->assertOk()->assertSee('ইনভয়েস');
    }

    public function test_an_unknown_language_value_falls_back_to_english(): void
    {
        $this->setting('admin_locale', 'fr');

        $this->assertSame('en', SiteSettingsHelper::adminLocale());
    }

    public function test_the_language_switch_is_in_site_setting_and_saves(): void
    {
        $this->needsIntl();
        $this->admin();

        $this->get('/admin/site-settings')->assertOk()->assertSee('Admin panel language')->assertSee('English');

        Livewire::test(SiteSettings::class)
            ->assertSet('data.admin_locale', 'en')
            ->set('data.admin_locale', 'bn')
            ->call('save')
            ->assertRedirect();

        $this->assertSame('bn', SiteSetting::where('setting_key', 'admin_locale')->value('setting_value'));
        $this->assertSame('bn', SiteSettingsHelper::adminLocale());

        // …and back to English.
        app()->setLocale('bn');
        Livewire::test(SiteSettings::class)->set('data.admin_locale', 'en')->call('save');
        $this->assertSame('en', SiteSettingsHelper::adminLocale());
    }

    public function test_field_labels_without_their_own_label_follow_the_language(): void
    {
        app()->setLocale('bn');
        $this->assertSame('গ্রাহকের নাম', TextInput::make('customer_name')->getLabel());

        app()->setLocale('en');
        $this->assertSame('Customer name', TextInput::make('customer_name')->getLabel());
    }

    public function test_every_bangla_text_of_the_admin_has_an_english_translation(): void
    {
        $en = json_decode(file_get_contents(base_path('lang/en.json')), true);
        $this->assertIsArray($en);

        $missing = [];

        $files = array_merge($this->filesUnder(app_path('Filament')), [app_path('Support/MediaReferences.php')], glob(resource_path('views/filament/pages/*.blade.php')));

        foreach ($files as $file) {
            preg_match_all('/__\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/u', file_get_contents($file), $m);

            foreach ($m[1] as $raw) {
                $key = stripcslashes($raw);

                if (preg_match('/[\x{0980}-\x{09FF}]/u', $key) && ! isset($en[$key])) {
                    $missing[$key] = basename($file);
                }
            }
        }

        $this->assertSame([], $missing, 'Add these to lang/en.json');
    }

    public function test_translations_keep_their_placeholders_and_are_not_empty(): void
    {
        foreach (json_decode(file_get_contents(base_path('lang/en.json')), true) as $bn => $en) {
            $this->assertNotSame('', trim($en), $bn);

            preg_match_all('/:[a-z]+/', $bn, $a);
            preg_match_all('/:[a-z]+/', $en, $b);
            $this->assertEqualsCanonicalizing($a[0], $b[0], "placeholders of: {$bn}");
        }
    }

    /** @return list<string> */
    private function filesUnder(string $dir): array
    {
        $out = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $out[] = $f->getPathname();
            }
        }

        return $out;
    }
}
