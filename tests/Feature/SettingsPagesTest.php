<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\ColorSettings;
use App\Filament\Pages\Settings\ContactSocialSettings;
use App\Filament\Pages\Settings\DeliverySettings;
use App\Filament\Pages\Settings\HeaderFooterSettings;
use App\Filament\Pages\Settings\HomepageSettings;
use App\Filament\Pages\Settings\SeoSettings;
use App\Filament\Pages\SiteSettings;
use App\Filament\Support\SettingsPage;
use App\Models\AdminUser;
use App\Models\SiteSetting;
use App\Support\SiteSettingsHelper;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/** The long "Site settings" page is now several pages, each with its own Save — and nothing was lost in the split. */
class SettingsPagesTest extends TestCase
{
    use CreatesShopData;

    /** Every key the single page used to manage. */
    private const OLD_KEYS = [
        'site_logo', 'footer_logo', 'site_favicon', 'site_name', 'site_name_en',
        'footer_description', 'footer_copyright',
        'hero_title', 'hero_subtitle',
        'side_card_1_title', 'side_card_1_text', 'side_card_2_title', 'side_card_2_text',
        'seo_title', 'seo_description', 'og_image',
        'order_cooldown_minutes', 'low_stock_threshold',
        'delivery_fee_dhaka', 'delivery_fee_outside', 'delivery_free_min',
        'color_brand', 'color_secondary', 'color_accent', 'color_background', 'color_text', 'color_success', 'color_error',
        'contact_phone', 'contact_whatsapp', 'contact_email', 'contact_address', 'contact_hours',
        'social_facebook', 'social_youtube', 'social_instagram', 'social_tiktok', 'social_twitter', 'social_linkedin', 'social_telegram',
        'home_sections', 'home_showcase_categories',
        'admin_locale',
    ];

    private const PAGES = [
        SiteSettings::class => 'site-settings',
        ColorSettings::class => 'site-colors',
        HomepageSettings::class => 'site-homepage',
        HeaderFooterSettings::class => 'site-footer',
        ContactSocialSettings::class => 'site-contact',
        DeliverySettings::class => 'site-delivery',
        SeoSettings::class => 'site-seo',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }

        $this->setting('admin_locale', 'bn');
        app()->setLocale('bn');
        $this->actingAs(AdminUser::create(['username' => 'boss', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');
    }

    private function keysOf(string $page): array
    {
        $method = new \ReflectionMethod($page, 'keys');

        return $method->invoke(app($page));
    }

    public function test_the_pages_together_manage_exactly_the_old_keys_and_no_key_twice(): void
    {
        $all = [];

        foreach (array_keys(self::PAGES) as $page) {
            $this->assertTrue(is_subclass_of($page, SettingsPage::class), $page);
            $all = array_merge($all, $this->keysOf($page));
        }

        $this->assertSame([], array_values(array_diff(self::OLD_KEYS, $all)), 'a setting was lost in the split');
        $this->assertSame([], array_values(array_diff($all, self::OLD_KEYS)), 'a page manages a key it should not');
        $this->assertSame(count($all), count(array_unique($all)), 'a key is on two pages');
    }

    public function test_every_page_opens_at_its_own_address_and_the_old_address_is_the_first_page(): void
    {
        foreach (self::PAGES as $page => $slug) {
            $this->get('/admin/'.$slug)->assertOk();
            $this->assertStringEndsWith('/admin/'.$slug, $page::getUrl());
        }
    }

    public function test_the_pages_are_listed_under_site_settings_in_the_settings_group(): void
    {
        $html = $this->get('/admin/site-settings')->assertOk()->getContent();

        foreach (['/admin/site-colors', '/admin/site-homepage', '/admin/site-footer', '/admin/site-contact', '/admin/site-delivery', '/admin/site-seo'] as $url) {
            $this->assertStringContainsString($url, $html, $url);
        }

        $this->assertSame('সাইট সেটিং', SiteSettings::getNavigationLabel());
        $this->assertNull(SiteSettings::getNavigationParentItem(), 'the first page is the parent');
        foreach (array_keys(self::PAGES) as $page) {
            if ($page !== SiteSettings::class) {
                $this->assertSame('সাইট সেটিং', $page::getNavigationParentItem(), $page);
                $this->assertSame('সেটিংস', $page::getNavigationGroup());
            }
        }
    }

    public function test_existing_values_show_up_on_the_right_page_after_the_split(): void
    {
        $this->setting('site_name', 'পুরনো দোকান');
        $this->setting('footer_copyright', 'পুরনো কপিরাইট');
        $this->setting('contact_phone', '01711111111');
        $this->setting('delivery_fee_dhaka', '55');
        $this->setting('seo_title', 'পুরনো এসইও');
        $this->setting('hero_title', 'পুরনো হিরো');
        $this->setting('color_brand', '#7c3aed');

        Livewire::test(SiteSettings::class)->assertSet('data.site_name', 'পুরনো দোকান');
        Livewire::test(HeaderFooterSettings::class)->assertSet('data.footer_copyright', 'পুরনো কপিরাইট');
        Livewire::test(ContactSocialSettings::class)->assertSet('data.contact_phone', '01711111111');
        Livewire::test(DeliverySettings::class)->assertSet('data.delivery_fee_dhaka', '55');
        Livewire::test(SeoSettings::class)->assertSet('data.seo_title', 'পুরনো এসইও');
        Livewire::test(HomepageSettings::class)->assertSet('data.hero_title', 'পুরনো হিরো');
        Livewire::test(ColorSettings::class)->assertSet('data.color_brand', '#7c3aed');
    }

    public function test_each_page_saves_only_its_own_settings(): void
    {
        $this->setting('site_name', 'রাখা নাম');
        $this->setting('contact_phone', '01711111111');
        $this->setting('footer_copyright', 'রাখা কপিরাইট');
        $this->setting('delivery_fee_dhaka', '55');
        $this->setting('seo_title', 'রাখা এসইও');
        $this->setting('hero_title', 'রাখা হিরো');
        $this->setting('color_brand', '#7c3aed');

        // Saving the delivery page changes delivery and nothing else.
        Livewire::test(DeliverySettings::class)
            ->set('data.delivery_fee_dhaka', '80')
            ->set('data.delivery_free_min', '2000')
            ->call('save')->assertHasNoFormErrors();

        $stored = fn (string $key) => SiteSetting::where('setting_key', $key)->value('setting_value');
        $this->assertSame('80', $stored('delivery_fee_dhaka'));
        $this->assertSame('2000', $stored('delivery_free_min'));
        $this->assertSame('রাখা নাম', $stored('site_name'));
        $this->assertSame('01711111111', $stored('contact_phone'));
        $this->assertSame('রাখা কপিরাইট', $stored('footer_copyright'));
        $this->assertSame('রাখা এসইও', $stored('seo_title'));
        $this->assertSame('রাখা হিরো', $stored('hero_title'));
        $this->assertSame('#7c3aed', $stored('color_brand'));

        // The same for each of the others, one at a time.
        Livewire::test(SeoSettings::class)->set('data.seo_title', 'নতুন এসইও')->call('save')->assertHasNoFormErrors();
        Livewire::test(ContactSocialSettings::class)->set('data.contact_phone', '01822222222')->set('data.social_facebook', 'https://facebook.com/shop')->call('save')->assertHasNoFormErrors();
        Livewire::test(HeaderFooterSettings::class)->set('data.footer_description', 'নতুন বিবরণ')->call('save')->assertHasNoFormErrors();
        Livewire::test(SiteSettings::class)->set('data.site_name_en', 'New Shop')->call('save')->assertHasNoFormErrors();

        $this->assertSame('নতুন এসইও', $stored('seo_title'));
        $this->assertSame('01822222222', $stored('contact_phone'));
        $this->assertSame('https://facebook.com/shop', SiteSettingsHelper::social('facebook'));
        $this->assertSame('নতুন বিবরণ', $stored('footer_description'));
        $this->assertSame('New Shop', $stored('site_name_en'));
        $this->assertSame('রাখা নাম', $stored('site_name'), 'General saved its own keys only');
        $this->assertSame('80', $stored('delivery_fee_dhaka'));
        $this->assertSame('#7c3aed', $stored('color_brand'));
        $this->assertSame('রাখা হিরো', $stored('hero_title'));
    }

    public function test_the_colour_default_is_stored_empty_and_reset_works_on_its_page(): void
    {
        $default = SiteSettingsHelper::colorDefaults()['brand'];

        Livewire::test(ColorSettings::class)->set('data.color_brand', '#112233')->call('save');
        $this->assertSame('#112233', SiteSetting::where('setting_key', 'color_brand')->value('setting_value'));

        Livewire::test(ColorSettings::class)->set('data.color_brand', $default)->call('save');
        $this->assertSame('', SiteSetting::where('setting_key', 'color_brand')->value('setting_value'));

        Livewire::test(ColorSettings::class)->set('data.color_brand', '#112233')->call('save')
            ->call('resetColors')->assertSet('data.color_brand', $default);
        $this->assertNull(SiteSetting::where('setting_key', 'color_brand')->first());
    }

    public function test_the_language_switch_still_lives_on_the_general_page_and_reloads(): void
    {
        Livewire::test(SiteSettings::class)->set('data.admin_locale', 'en')->call('save')->assertRedirect();

        $this->assertSame('en', SiteSettingsHelper::adminLocale());
    }

    public function test_managers_cannot_open_any_of_the_settings_pages(): void
    {
        $this->flushSession();
        $this->actingAs(AdminUser::create(['username' => 'mgr', 'password' => 'secret-pass-1', 'role' => 'manager']), 'admin');

        foreach (self::PAGES as $slug) {
            $this->get('/admin/'.$slug)->assertForbidden();
        }
    }
}
