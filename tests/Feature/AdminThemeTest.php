<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Models\AdminUser;
use App\Models\SiteSetting;
use App\Support\AdminTheme;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/** The admin's own look: dark sidebar, groups as menu entries, footer with the signed-in admin, View site, and Admin colours. */
class AdminThemeTest extends TestCase
{
    use CreatesShopData;

    private function admin(string $role = AdminUser::ROLE_SUPER_ADMIN, string $name = 'Ariful Islam BD'): AdminUser
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }

        $this->setting('admin_locale', 'en');
        app()->setLocale('en');

        return tap(AdminUser::create(['username' => "u{$role}", 'name' => $name, 'password' => 'secret-pass-1', 'role' => $role]), fn ($a) => $this->actingAs($a, 'admin'));
    }

    // ── the colours ──────────────────────────────────────────────────────

    public function test_the_defaults_are_a_dark_navy_sidebar_and_a_bright_blue(): void
    {
        $this->assertSame('#0b1437', AdminTheme::sidebar());
        $this->assertSame('#2f6df6', AdminTheme::primary());
        $this->assertTrue(AdminTheme::sidebarIsDark());
        $this->assertSame('#f1f5f9', AdminTheme::sidebarVars()['--sb-text'], 'light text on the dark sidebar');
    }

    public function test_the_admin_colours_are_separate_from_the_shops_brand_colours(): void
    {
        $this->setting('color_brand', '#16a34a');
        $this->setting('color_secondary', '#7c2d12');

        $this->assertSame('#2f6df6', AdminTheme::primary(), 'the shop brand colour no longer drives the admin');
        $this->assertSame('#0b1437', AdminTheme::sidebar());

        $this->setting('admin_color_primary', '#e11d48');
        $this->setting('admin_color_sidebar', '#14532d');
        $this->assertSame('#e11d48', AdminTheme::primary());
        $this->assertSame('#14532d', AdminTheme::sidebar());
        $this->assertSame('#16a34a', \App\Support\SiteSettingsHelper::color('brand'), 'and the shop colour stays as it was');
    }

    public function test_a_light_sidebar_gets_dark_text_and_a_bad_value_falls_back(): void
    {
        $this->setting('admin_color_sidebar', '#f8fafc');
        $this->assertFalse(AdminTheme::sidebarIsDark());
        $vars = AdminTheme::sidebarVars();
        $this->assertSame('#f8fafc', $vars['--sb-bg']);
        $this->assertSame('#0f172a', $vars['--sb-text'], 'dark text on a light sidebar');

        $this->setting('admin_color_sidebar', 'javascript:alert(1)');
        $this->setting('admin_color_primary', 'red; background:url(x)');
        $this->assertSame('#0b1437', AdminTheme::sidebar());
        $this->assertSame('#2f6df6', AdminTheme::primary());
    }

    public function test_the_colours_reach_the_admin_pages(): void
    {
        $this->admin();
        $this->setting('admin_color_sidebar', '#14532d');

        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('--sb-bg: #14532d', $html);
        $this->assertStringContainsString('--sb-text: #f1f5f9', $html);
        $this->assertStringContainsString('.fi-sidebar-item.fi-active', $html);
    }

    // ── Site settings → General → Admin colours ──────────────────────────

    public function test_the_colours_are_set_from_site_settings_and_a_default_is_stored_empty(): void
    {
        $this->admin();

        Livewire::test(SiteSettings::class)
            ->assertSee('Admin colours')->assertSee('Sidebar colour')->assertSee('Admin main colour')
            ->assertSet('data.admin_color_sidebar', '#0b1437')
            ->assertSet('data.admin_color_primary', '#2f6df6')
            ->set('data.admin_color_sidebar', '#1e1b4b')
            ->set('data.admin_color_primary', '#f97316')
            ->call('save')->assertHasNoErrors()->assertRedirect();

        $this->assertSame('#1e1b4b', AdminTheme::sidebar());
        $this->assertSame('#f97316', AdminTheme::primary());

        // Back to the defaults by typing them: nothing is kept in the table.
        Livewire::test(SiteSettings::class)
            ->set('data.admin_color_sidebar', '#0B1437')
            ->set('data.admin_color_primary', '#2f6df6')
            ->call('save');
        $this->assertSame('', SiteSetting::where('setting_key', 'admin_color_sidebar')->value('setting_value'));
        $this->assertSame('', SiteSetting::where('setting_key', 'admin_color_primary')->value('setting_value'));
    }

    public function test_only_real_colours_are_accepted_and_reset_restores_the_defaults(): void
    {
        $this->admin();

        foreach (['red', '#12345', '#gggggg', 'url(x)', ''] as $bad) {
            Livewire::test(SiteSettings::class)->set('data.admin_color_sidebar', $bad)->call('save')->assertHasErrors(['data.admin_color_sidebar']);
        }
        $this->assertSame('#0b1437', AdminTheme::sidebar());

        $this->setting('admin_color_sidebar', '#14532d');
        $this->setting('admin_color_primary', '#e11d48');

        Livewire::test(SiteSettings::class)->call('resetAdminColors')->assertRedirect();

        $this->assertSame('#0b1437', AdminTheme::sidebar());
        $this->assertSame('#2f6df6', AdminTheme::primary());
        $this->assertNull(SiteSetting::where('setting_key', 'admin_color_sidebar')->first());
    }

    // ── menu, footer, View site ──────────────────────────────────────────

    public function test_the_groups_are_menu_entries_with_icons_that_open_their_submenu(): void
    {
        $this->admin();

        $html = $this->get('/admin')->assertOk()->getContent();

        foreach (['Sales', 'Catalog', 'Marketing', 'Content', 'Customers', 'Settings'] as $group) {
            $this->assertMatchesRegularExpression('#fi-sidebar-group-btn.*?<svg.*?fi-sidebar-group-label[^>]*>\s*'.$group.'\s*<#s', $html, "{$group} has an icon");
        }

        // They can be opened and closed (an arrow button each) and start closed.
        $this->assertGreaterThanOrEqual(6, substr_count($html, 'fi-sidebar-group-collapse-btn'));
        $this->assertStringContainsString('Orders', $html);
    }

    public function test_the_sidebar_foot_shows_who_is_signed_in_view_site_and_log_out(): void
    {
        $this->admin(AdminUser::ROLE_MANAGER, 'Rahim Uddin');

        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('admin-sb-foot', $html);
        $this->assertStringContainsString('Rahim Uddin', $html);
        $this->assertStringContainsString('>R<', str_replace(["\n", ' '], '', $html), 'the avatar shows the initial');
        $this->assertStringContainsString('Manager', $html, 'the role, without the explanation in brackets');
        $this->assertStringNotContainsString('Manager (everything except', $html);

        // View site opens the website in a new tab.
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(url('/'), '#').'" target="_blank" rel="noopener" class="admin-sb-link">#', $html);

        // Log out is a real POST form with the CSRF token.
        $this->assertMatchesRegularExpression('#<form method="POST" action="[^"]*logout">\s*<input type="hidden" name="_token"#', $html);
        $this->assertStringContainsString('Log out', $html);
    }

    public function test_the_topbar_has_an_icon_that_opens_the_website_in_a_new_tab(): void
    {
        $this->admin();

        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(url('/'), '#').'" target="_blank" rel="noopener" class="admin-viewsite-btn"[^>]*title="View website \(new tab\)"#', $html);
    }

    public function test_every_role_still_gets_a_working_menu(): void
    {
        foreach ([AdminUser::ROLE_SUPER_ADMIN, AdminUser::ROLE_MANAGER, AdminUser::ROLE_ORDER_STAFF] as $role) {
            $this->flushSession();
            $this->admin($role);

            $this->get('/admin')->assertOk()->assertSee('Dashboard');
        }
    }
}
