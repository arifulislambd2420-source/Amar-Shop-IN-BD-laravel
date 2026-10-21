<?php

namespace Tests\Feature;

use App\Livewire\Checkout\CheckoutForm;
use App\Models\IpBlock;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Services\CartService;
use App\Support\AutoBlock;
use App\Support\ShopTime;
use App\Support\SiteSettingsHelper;
use App\Support\TrustedProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/** Fixes from the full-site audit: settings read once, proxies, Bangladesh time, coupon guessing, passwords, tap targets, server config. */
class SiteAuditFixesTest extends TestCase
{
    use CreatesShopData;

    // ── settings: one read, not one per key ──────────────────────────────

    public function test_all_settings_are_read_with_one_query_and_a_change_shows_at_once(): void
    {
        SiteSetting::create(['setting_key' => 'site_name', 'setting_value' => 'প্রথম']);
        SiteSetting::create(['setting_key' => 'contact_phone', 'setting_value' => '01711111111']);
        SiteSettingsHelper::forget();

        DB::enableQueryLog();
        $values = [SiteSettingsHelper::get('site_name'), SiteSettingsHelper::get('contact_phone'), SiteSettingsHelper::get('nothing'), SiteSettingsHelper::get('social_facebook', 'dflt')];
        for ($i = 0; $i < 30; $i++) {
            SiteSettingsHelper::get('site_name');
            SiteSettingsHelper::get('color_brand');
        }
        $settingQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'site_settings'))->count();
        DB::disableQueryLog();

        $this->assertSame(['প্রথম', '01711111111', null, 'dflt'], $values);
        $this->assertSame(1, $settingQueries, 'the whole table is loaded once, not once per key');

        // Through the model, a change is visible immediately; so is a delete.
        SiteSetting::where('setting_key', 'site_name')->first()->update(['setting_value' => 'দ্বিতীয়']);
        $this->assertSame('দ্বিতীয়', SiteSettingsHelper::get('site_name'));
        SiteSetting::where('setting_key', 'site_name')->first()->delete();
        $this->assertNull(SiteSettingsHelper::get('site_name'));
    }

    public function test_a_page_view_does_not_read_settings_key_by_key(): void
    {
        $this->setting('site_name', 'টেস্ট শপ');
        $this->get('/')->assertOk(); // warm

        DB::enableQueryLog();
        $this->get('/contact')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(30, $queries, "a plain page used {$queries} queries");
    }

    // ── proxies ──────────────────────────────────────────────────────────

    public function test_trusted_proxies_come_from_config_so_they_survive_config_cache(): void
    {
        config(['proxies.trusted' => '203.0.113.7, 10.1.0.0/16']);
        $this->assertSame(['203.0.113.7', '10.1.0.0/16'], TrustedProxies::resolve());

        config(['proxies.trusted' => '*']);
        $this->assertSame('*', TrustedProxies::resolve());

        config(['proxies.trusted' => '']);
        $this->assertSame(TrustedProxies::PRIVATE_RANGES, TrustedProxies::resolve());
        $this->assertSame('', config('proxies.trusted'), 'the key exists in config/proxies.php');
    }

    public function test_the_flood_block_never_counts_visitors_behind_an_untrusted_proxy(): void
    {
        $this->setting('autoblock_max_requests', '10');

        // A proxy at a public address that is not trusted: every visitor shows as that one IP.
        for ($i = 0; $i < 25; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '45.67.89.10'])
                ->withHeaders(['X-Forwarded-For' => '198.51.100.'.$i, 'User-Agent' => 'Mozilla/5.0 Chrome/120'])
                ->get('/')->assertOk();
        }
        $this->assertNull(IpBlock::where('ip', '45.67.89.10')->first());

        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '45.67.89.10', 'HTTP_X_FORWARDED_FOR' => '198.51.100.9']);
        $this->assertTrue(AutoBlock::behindUntrustedProxy($request));
        $this->assertFalse(AutoBlock::behindUntrustedProxy(Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '45.67.89.10'])));
    }

    // ── Bangladesh time ──────────────────────────────────────────────────

    public function test_shop_days_follow_bangladesh_time_while_the_database_stays_utc(): void
    {
        // 22:00 UTC on 21 Oct is 04:00 on 22 Oct in Dhaka.
        Carbon::setTestNow(Carbon::parse('2026-10-21 22:00:00', 'UTC'));

        $this->assertSame('2026-10-22', ShopTime::today());
        $this->assertSame('2026-10-21 18:00:00', ShopTime::dayStart(0)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-20 18:00:00', ShopTime::dayStart(1)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-30 18:00:00', ShopTime::monthStart()->format('Y-m-d H:i:s')); // 1 Oct 00:00 in Dhaka

        $stored = Carbon::parse('2026-10-21 15:00:00', 'UTC'); // 9 PM in Dhaka
        $this->assertSame('2026-10-21 21:00', ShopTime::local($stored)->format('Y-m-d H:i'));
        $this->assertNull(ShopTime::local(null));

        Carbon::setTestNow();
    }

    public function test_the_dashboard_counts_an_order_from_after_midnight_in_dhaka_as_today(): void
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }

        Carbon::setTestNow(Carbon::parse('2026-10-21 22:00:00', 'UTC')); // 04:00 on the 22nd in Dhaka

        $late = $this->placeOrder();       // 21 Oct 20:00 UTC = 02:00 on the 22nd in Dhaka → today
        Order::whereKey($late->id)->update(['created_at' => '2026-10-21 20:00:00']);
        $early = $this->placeOrder(customer: ['phone' => '01812345678']); // 21 Oct 12:00 UTC = 18:00 on the 21st → yesterday
        Order::whereKey($early->id)->update(['created_at' => '2026-10-21 12:00:00']);

        $this->setting('admin_locale', 'en');
        app()->setLocale('en');
        $this->actingAs(\App\Models\AdminUser::create(['username' => 'boss', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');

        $stats = Livewire::test(\App\Filament\Widgets\StatsOverview::class);
        $stats->assertSee("Today's orders")->assertSee('Yesterday 1');

        Carbon::setTestNow();
    }

    public function test_customers_see_order_times_and_invoice_dates_in_bangladesh_time(): void
    {
        $order = $this->placeOrder();
        Order::whereKey($order->id)->update(['created_at' => '2026-03-05 15:00:00']); // 9 PM in Dhaka

        $this->get('/order/'.$order->order_token.'/invoice')->assertOk()->assertSee('05 Mar 2026, 09:00 PM');

        // A 21:30 UTC order is already the next day in Dhaka.
        Order::whereKey($order->id)->update(['created_at' => '2026-03-05 21:30:00']);
        $this->get('/order/'.$order->order_token.'/invoice')->assertOk()->assertSee('06 Mar 2026, 03:30 AM');
    }

    public function test_invoice_numbers_carry_the_shop_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-05 21:30:00', 'UTC')); // already 6 March in Dhaka

        $this->assertStringStartsWith('INV-260306-', $this->placeOrder()->invoice_no);

        Carbon::setTestNow();
    }

    // ── coupon guessing, passwords ───────────────────────────────────────

    public function test_guessing_coupon_codes_is_slowed_down(): void
    {
        $p = $this->product();
        app(CartService::class)->add($p->id, 1);
        $checkout = Livewire::test(CheckoutForm::class);

        for ($i = 1; $i <= 15; $i++) {
            $checkout->set('couponCode', 'GUESS'.$i)->call('applyCoupon')->assertSet('couponError', 'কুপন কোডটি সঠিক নয়।');
        }

        $checkout->set('couponCode', 'GUESS16')->call('applyCoupon');
        $this->assertStringContainsString('অনেকবার চেষ্টা', $checkout->get('couponError'));
    }

    public function test_customer_passwords_need_eight_characters_but_older_short_ones_still_log_in(): void
    {
        $this->post('/customer/register', ['name' => 'নতুন', 'phone' => '01712345678', 'password' => '1234567'])->assertSessionHasErrors('password');
        $this->assertSame(0, \App\Models\User::count());

        $this->post('/customer/register', ['name' => 'নতুন', 'phone' => '01712345678', 'password' => '12345678'])->assertSessionDoesntHaveErrors();
        $this->assertSame(1, \App\Models\User::count());

        // An account made when six characters were enough can still sign in.
        \App\Models\User::create(['name' => 'পুরনো', 'phone' => '01812345678', 'password' => \Illuminate\Support\Facades\Hash::make('abc123')]);
        auth('web')->logout();
        $this->post('/customer/login', ['phone' => '01812345678', 'password' => 'abc123'])->assertRedirect();
        $this->assertAuthenticated('web');
    }

    // ── tap targets, server config ───────────────────────────────────────

    public function test_footer_links_are_big_enough_to_tap(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $footer = substr($html, strpos($html, '<footer'));

        $this->assertGreaterThanOrEqual(8, substr_count($footer, 'inline-flex min-h-9 items-center'));
    }

    public function test_the_web_server_config_compresses_caches_and_hides_dotfiles(): void
    {
        $htaccess = file_get_contents(public_path('.htaccess'));

        $this->assertStringContainsString('mod_deflate', $htaccess);
        $this->assertStringContainsString('mod_expires', $htaccess);
        $this->assertStringContainsString('ExpiresByType text/css "access plus 1 year"', $htaccess);
        $this->assertStringContainsString('(^|/)\.(?!well-known/) - [F,L]', $htaccess);
        $this->assertStringNotContainsString('ExpiresByType text/html', $htaccess, 'pages themselves are never cached');
    }
}
