<?php

namespace Tests\Feature;

use App\Filament\Pages\AdminAddress;
use App\Models\AdminUser;
use App\Support\AdminPath;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin panel can live at another address than /admin. The path is read while
 * the app boots, so these tests write it to a temp file and boot the app again.
 */
class AdminPathTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = sys_get_temp_dir().DIRECTORY_SEPARATOR.'admin-path-'.uniqid().'.txt';
        AdminPath::useFile($this->file);
    }

    protected function tearDown(): void
    {
        AdminPath::useFile(null);
        File::delete($this->file);
        parent::tearDown();
    }

    /** Boot the app again so the panel's routes pick up the file (fresh in-memory database). */
    private function reboot(): void
    {
        $this->refreshApplication();
        $this->artisan('migrate', ['--force' => true]);
    }

    public function test_the_default_is_admin(): void
    {
        $this->assertSame('admin', AdminPath::current());
        $this->get('/admin/login')->assertOk();
    }

    public function test_a_custom_path_serves_the_login_and_the_old_admin_is_a_404(): void
    {
        $this->assertTrue(AdminPath::set('my-panel-2026'));
        $this->reboot();

        $this->get('/my-panel-2026/login')->assertOk();
        $this->get('/my-panel-2026')->assertRedirect(); // to the login for guests
        $this->get('/admin')->assertNotFound();
        $this->get('/admin/login')->assertNotFound();
        $this->get('/admin/orders')->assertNotFound();
    }

    public function test_the_secret_path_is_not_in_robots_txt_but_the_default_is(): void
    {
        $this->get('/robots.txt')->assertSee('Disallow: /admin', false);

        AdminPath::set('my-panel-2026');
        $this->reboot();

        $this->get('/robots.txt')->assertOk()->assertDontSee('my-panel-2026')->assertDontSee('Disallow: /admin', false);
    }

    public function test_the_reset_command_brings_admin_back(): void
    {
        AdminPath::set('my-panel-2026');
        $this->reboot();
        $this->get('/admin/login')->assertNotFound();

        $this->artisan('admin:reset-path')->assertSuccessful();
        $this->assertFileDoesNotExist($this->file);

        $this->reboot();
        $this->get('/admin/login')->assertOk();
        $this->get('/my-panel-2026/login')->assertNotFound();
    }

    public function test_only_lowercase_letters_digits_and_hyphens_are_allowed(): void
    {
        foreach (['my-panel-2026', 'a1b', 'secret', 'panel-9-x'] as $ok) {
            $this->assertNull(AdminPath::validate($ok), $ok);
        }

        foreach (['MyPanel', 'my_panel', 'my panel', 'my/panel', '-panel', 'panel-', 'my--panel', 'ab', str_repeat('a', 41), 'প্যানেল', 'panel.php', ''] as $bad) {
            $this->assertNotNull(AdminPath::validate($bad), var_export($bad, true));
        }
    }

    public function test_names_the_site_already_uses_are_refused(): void
    {
        foreach (['shop', 'cart', 'checkout', 'product', 'blog', 'customer', 'track', 'contact', 'lp', 'order', 'payment', 'brands', 'offers', 'sitemap', 'robots', 'storage', 'build', 'livewire', 'api', 'up', 'login', 'vendor'] as $taken) {
            $this->assertNotNull(AdminPath::validate($taken), $taken);
        }

        $this->assertNull(AdminPath::validate('admin'), 'the default is always allowed (it puts /admin back)');
    }

    public function test_a_bad_file_falls_back_to_admin(): void
    {
        file_put_contents($this->file, "Bad Path!\n");
        AdminPath::useFile($this->file);

        $this->assertSame('admin', AdminPath::current());
    }

    public function test_the_admin_page_confirms_with_the_new_link_then_saves_and_goes_there(): void
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }

        $this->actingAs(AdminUser::create(['username' => 'boss', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');
        app()->setLocale('en');

        $page = Livewire::test(AdminAddress::class)->assertSee('/admin');

        // Rejected names do not save.
        $page->fillForm(['path' => 'Shop'])->call('save')->assertHasFormErrors(['path']);
        $page->fillForm(['path' => 'shop'])->call('save')->assertHasFormErrors(['path']);
        $this->assertFileDoesNotExist($this->file);

        // The confirmation shows the exact new login link and the warning.
        $page->fillForm(['path' => 'my-panel-2026'])
            ->mountAction('save')
            ->assertActionMounted('save')
            ->assertMountedActionModalSee(url('/my-panel-2026/login'))
            ->assertMountedActionModalSee('write it down')
            ->callMountedAction()
            ->assertRedirect(url('/my-panel-2026'));

        $this->assertSame('my-panel-2026', trim(file_get_contents($this->file)));
    }

    public function test_managers_cannot_open_the_page(): void
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }

        $this->actingAs(AdminUser::create(['username' => 'mgr', 'password' => 'secret-pass-1', 'role' => 'manager']), 'admin');
        $this->get('/admin/admin-address')->assertForbidden();
    }
}
