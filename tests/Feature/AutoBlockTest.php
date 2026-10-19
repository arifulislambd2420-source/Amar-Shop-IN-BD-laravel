<?php

namespace Tests\Feature;

use App\Filament\Resources\IpBlocks\Pages\ManageIpBlocks;
use App\Models\AdminUser;
use App\Models\IpBlock;
use App\Support\AutoBlock;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/** An IP that floods the shop is blocked for a while; admins are never caught; the limits and the list are in the admin. */
class AutoBlockTest extends TestCase
{
    use CreatesShopData;

    private const IP = '45.67.89.10';

    /** Hit the storefront as a visitor from $ip. */
    private function visit(string $path = '/', string $ip = self::IP, string $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->withHeader('User-Agent', $ua)->get($path);
    }

    private function limit(int $max = 10): void
    {
        $this->setting('autoblock_max_requests', (string) $max);
        $this->setting('autoblock_window_seconds', '60');
        $this->setting('autoblock_block_minutes', '30');
    }

    public function test_a_flood_gets_a_429_then_a_403_and_shows_in_the_list_as_automatic_with_an_end_time(): void
    {
        $this->limit(10);

        for ($i = 1; $i <= 10; $i++) {
            $this->visit()->assertOk();
        }

        $this->visit()->assertStatus(429)->assertHeader('Retry-After', '1800');
        $this->visit()->assertForbidden();
        $this->visit('/shop')->assertForbidden();

        $block = IpBlock::where('ip', self::IP)->firstOrFail();
        $this->assertTrue($block->is_auto);
        $this->assertSame(11, $block->hits);
        $this->assertStringContainsString('11 requests in 60s', $block->reason);
        $this->assertEqualsWithDelta(now()->addMinutes(30)->timestamp, $block->expires_at->timestamp, 5);

        // Other visitors are untouched.
        $this->visit('/', '45.67.89.11')->assertOk();
    }

    public function test_the_block_ends_by_itself(): void
    {
        $this->limit(10);
        for ($i = 0; $i < 11; $i++) {
            $this->visit();
        }
        $this->visit()->assertForbidden();

        $this->travel(29)->minutes();
        $this->visit()->assertForbidden();

        $this->travel(2)->minutes();
        $this->visit()->assertOk();

        // The tidy-up removes the finished row.
        $this->assertSame(1, IpBlock::expired()->count());
        IpBlock::expired()->get()->each->delete();
        $this->assertNull(IpBlock::where('ip', self::IP)->first());
    }

    public function test_requests_spread_over_time_are_fine(): void
    {
        $this->limit(10);

        for ($i = 0; $i < 9; $i++) {
            $this->visit()->assertOk();
        }

        $this->travel(61)->seconds();

        for ($i = 0; $i < 9; $i++) {
            $this->visit()->assertOk();
        }

        $this->assertNull(IpBlock::where('ip', self::IP)->first());
    }

    public function test_a_permanent_manual_block_is_not_replaced_by_a_temporary_one(): void
    {
        IpBlock::create(['ip' => self::IP, 'reason' => 'spammer']);

        AutoBlock::hit(request()->duplicate(server: ['REMOTE_ADDR' => self::IP]));
        $this->limit(10);
        $this->assertFalse(IpBlock::where('ip', self::IP)->first()->is_auto);

        // (blocked at once, from the manual entry)
        $this->visit()->assertForbidden();
        $this->assertNull(IpBlock::where('ip', self::IP)->first()->expires_at);
    }

    // ── never blocked ────────────────────────────────────────────────────

    public function test_admins_are_never_blocked(): void
    {
        $this->limit(10);

        // An IP an admin used for the panel in the last 30 days.
        AutoBlock::rememberAdminIp('45.67.89.20');
        for ($i = 0; $i < 30; $i++) {
            $this->visit('/', '45.67.89.20')->assertOk();
        }
        $this->assertNull(IpBlock::where('ip', '45.67.89.20')->first());

        // A signed-in admin on any IP.
        $admin = AdminUser::create(['username' => 'boss', 'password' => 'secret-pass-1', 'role' => 'super_admin']);
        $this->actingAs($admin, 'admin');
        for ($i = 0; $i < 30; $i++) {
            $this->visit('/', '45.67.89.30')->assertOk();
        }
        $this->assertNull(IpBlock::where('ip', '45.67.89.30')->first());
    }

    public function test_private_and_reserved_addresses_and_media_requests_are_never_counted(): void
    {
        $this->limit(10);

        foreach (['127.0.0.1', '10.0.0.5', '192.168.1.20', '172.16.4.4'] as $ip) {
            for ($i = 0; $i < 25; $i++) {
                $this->visit('/', $ip)->assertOk();
            }
            $this->assertNull(IpBlock::where('ip', $ip)->first(), $ip);
        }

        // A page can pull dozens of pictures through the fallback route.
        for ($i = 0; $i < 30; $i++) {
            $this->visit('/storage/media/none-'.$i.'.png');
        }
        $this->assertNull(IpBlock::where('ip', self::IP)->first());
    }

    public function test_it_can_be_switched_off(): void
    {
        $this->limit(10);
        $this->setting('autoblock_enabled', '0');

        for ($i = 0; $i < 25; $i++) {
            $this->visit()->assertOk();
        }
        $this->assertNull(IpBlock::where('ip', self::IP)->first());
    }

    public function test_the_defaults_and_limits_of_the_settings(): void
    {
        $this->assertTrue(AutoBlock::enabled());
        $this->assertSame(150, AutoBlock::maxRequests());
        $this->assertSame(60, AutoBlock::windowSeconds());
        $this->assertSame(30, AutoBlock::blockMinutes());

        $this->setting('autoblock_max_requests', '1');
        $this->setting('autoblock_window_seconds', '999999');
        $this->setting('autoblock_block_minutes', 'abc');
        $this->assertSame(10, AutoBlock::maxRequests(), 'never below 10');
        $this->assertSame(3600, AutoBlock::windowSeconds());
        $this->assertSame(30, AutoBlock::blockMinutes());
    }

    // ── the admin ────────────────────────────────────────────────────────

    private function asSuperAdmin(): AdminUser
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }

        $this->setting('admin_locale', 'bn');
        app()->setLocale('bn');

        return tap(AdminUser::create(['username' => 'boss', 'password' => 'secret-pass-1', 'role' => 'super_admin']), fn ($a) => $this->actingAs($a, 'admin'));
    }

    public function test_the_limits_are_set_from_the_ip_block_page(): void
    {
        $this->asSuperAdmin();

        Livewire::test(ManageIpBlocks::class)
            ->callAction('autoBlockSettings', ['autoblock_enabled' => true, 'autoblock_max_requests' => 300, 'autoblock_window_seconds' => 120, 'autoblock_block_minutes' => 90])
            ->assertHasNoFormErrors();

        $this->assertSame(300, AutoBlock::maxRequests());
        $this->assertSame(120, AutoBlock::windowSeconds());
        $this->assertSame(90, AutoBlock::blockMinutes());

        Livewire::test(ManageIpBlocks::class)
            ->callAction('autoBlockSettings', ['autoblock_enabled' => false, 'autoblock_max_requests' => 300, 'autoblock_window_seconds' => 120, 'autoblock_block_minutes' => 90]);
        $this->assertFalse(AutoBlock::enabled());

        // Out-of-range values are refused.
        foreach ([['autoblock_max_requests' => 5], ['autoblock_window_seconds' => 1], ['autoblock_block_minutes' => 0], ['autoblock_max_requests' => 'many']] as $bad) {
            Livewire::test(ManageIpBlocks::class)
                ->callAction('autoBlockSettings', $bad + ['autoblock_enabled' => true, 'autoblock_max_requests' => 300, 'autoblock_window_seconds' => 120, 'autoblock_block_minutes' => 90])
                ->assertHasFormErrors();
        }
    }

    public function test_the_list_shows_automatic_blocks_with_status_and_filters_them(): void
    {
        $this->asSuperAdmin();
        $manual = IpBlock::create(['ip' => '45.67.89.40', 'reason' => 'spam']);
        $auto = IpBlock::create(['ip' => '45.67.89.41', 'reason' => 'Auto: 200 requests in 60s', 'is_auto' => true, 'hits' => 200, 'expires_at' => now()->addMinutes(20)]);
        $old = IpBlock::create(['ip' => '45.67.89.42', 'reason' => 'Auto: 200 requests in 60s', 'is_auto' => true, 'expires_at' => now()->subMinute()]);

        $this->assertTrue($manual->isActive());
        $this->assertTrue($auto->isActive());
        $this->assertFalse($old->isActive());

        Livewire::test(ManageIpBlocks::class)
            ->assertCanSeeTableRecords([$manual, $auto, $old])
            ->assertSee('স্বয়ংক্রিয়')
            ->assertSee('মেয়াদ শেষ')
            ->assertSee('স্থায়ী')
            ->filterTable('is_auto', true)
            ->assertCanSeeTableRecords([$auto, $old])
            ->assertCanNotSeeTableRecords([$manual]);

        // An expired automatic block no longer blocks.
        $this->visit('/', '45.67.89.42')->assertOk();
        $this->visit('/', '45.67.89.41')->assertForbidden();
        $this->visit('/', '45.67.89.40')->assertForbidden();
    }

    public function test_an_admin_cannot_block_their_own_or_another_admins_ip(): void
    {
        $this->asSuperAdmin();
        AutoBlock::rememberAdminIp('45.67.89.50');

        // 127.0.0.1 is the IP of this request.
        Livewire::test(ManageIpBlocks::class)
            ->callAction('create', ['ip' => '127.0.0.1', 'reason' => 'oops'])
            ->assertHasFormErrors(['ip']);
        Livewire::test(ManageIpBlocks::class)
            ->callAction('create', ['ip' => '45.67.89.50'])
            ->assertHasFormErrors(['ip']);
        Livewire::test(ManageIpBlocks::class)
            ->callAction('create', ['ip' => 'not-an-ip'])
            ->assertHasFormErrors(['ip']);

        Livewire::test(ManageIpBlocks::class)
            ->callAction('create', ['ip' => '45.67.89.60', 'reason' => 'spam'])
            ->assertHasNoFormErrors();
        $this->assertNotNull(IpBlock::where('ip', '45.67.89.60')->first());
    }

    public function test_admin_requests_remember_the_admin_ip(): void
    {
        $this->asSuperAdmin();

        $this->withServerVariables(['REMOTE_ADDR' => '45.67.89.70'])->get('/admin')->assertOk();

        $this->assertTrue(AutoBlock::isAdminIp('45.67.89.70'));
    }

    // ── blocked IPs and bots get no tracking ─────────────────────────────

    public function test_a_blocked_ip_gets_no_pages_and_so_no_tracking(): void
    {
        $this->setting('gtm_id', 'GTM-TEST123');
        IpBlock::create(['ip' => self::IP, 'reason' => 'bad']);

        $response = $this->visit()->assertForbidden();
        $this->assertStringNotContainsString('googletagmanager', $response->getContent());
    }
}
