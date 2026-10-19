<?php

namespace Tests\Feature;

use App\Filament\Pages\GtmSettings;
use App\Livewire\Checkout\CheckoutForm;
use App\Livewire\Landing\LandingOrderForm;
use App\Livewire\Product\AddToCart;
use App\Models\AdminUser;
use App\Models\Category;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\SiteSetting;
use App\Services\CartService;
use App\Services\OrderService;
use App\Support\Tracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/**
 * Tracking part 1: the fixes (order-now AddToCart, variant-aware ViewContent, once-per-cart InitiateCheckout, …),
 * the new events (Search, ViewCategory, CompleteRegistration, OrderPlaced, interest), what is saved with an order,
 * and keeping bots out of tracking.
 */
class TrackingInterestTest extends TestCase
{
    use CreatesShopData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setting('gtm_id', 'GTM-TEST123');
        $this->setting('meta_pixel_id', '123456789012345');
    }

    /** A product with two sizes: 500g (৳1300, in stock) and 1kg (৳2500). */
    private function honey(): \App\Models\Product
    {
        $category = Category::create(['name' => 'মধু', 'slug' => 'honey']);
        $p = $this->product(['name' => 'সুন্দরবন মধু', 'slug' => 'honey-1', 'price' => 2500, 'stock' => 0, 'category_id' => $category->id]);
        ProductVariant::create(['product_id' => $p->id, 'label' => '৫০০g', 'price' => 1300, 'stock' => 5]);
        ProductVariant::create(['product_id' => $p->id, 'label' => '১kg', 'price' => 2500, 'stock' => 5]);

        return $p->fresh(['variants', 'category']);
    }

    // ── ViewContent / AddToCart with the chosen variant ──────────────────

    public function test_view_content_starts_on_the_preselected_variant_with_its_price_name_and_category(): void
    {
        $p = $this->honey();

        $html = $this->get('/product/'.$p->slug)->assertOk()->getContent();

        preg_match("/trackEvent\('view_item', (\{.*?\})\);/s", $html, $m);
        $payload = json_decode($m[1], true);

        $this->assertSame(1300.0, (float) $payload['value'], 'the 500g price, not the product\'s base price');
        $this->assertSame('সুন্দরবন মধু (৫০০g)', $payload['items'][0]['item_name']);
        $this->assertSame('৫০০g', $payload['items'][0]['item_variant']);
        $this->assertSame($p->id, $payload['items'][0]['item_id'], 'item_id stays the product id (it matches the feed)');
        $this->assertSame('সুন্দরবন মধু', $payload['content_name']);
        $this->assertSame('মধু', $payload['content_category']);
        $this->assertSame('BDT', $payload['currency']);
    }

    public function test_picking_a_size_or_colour_sends_view_content_again(): void
    {
        $p = $this->honey();
        $big = $p->variants->firstWhere('label', '১kg');

        Livewire::test(AddToCart::class, ['productId' => $p->id, 'mode' => 'detail'])
            ->call('selectVariant', $big->id)
            ->assertDispatched('gtm:view_item', fn ($event, $params) => $params['ecommerce']['value'] == 2500
                && $params['ecommerce']['items'][0]['item_name'] === 'সুন্দরবন মধু (১kg)'
                && $params['ecommerce']['items'][0]['item_variant'] === '১kg'
                && $params['ecommerce']['content_category'] === 'মধু');
    }

    public function test_a_product_card_does_not_send_view_content_when_a_size_is_picked_and_tracking_off_sends_nothing(): void
    {
        $p = $this->honey();
        $big = $p->variants->firstWhere('label', '১kg');

        Livewire::test(AddToCart::class, ['productId' => $p->id, 'mode' => 'card'])
            ->call('selectVariant', $big->id)
            ->assertNotDispatched('gtm:view_item');

        SiteSetting::whereIn('setting_key', ['gtm_id', 'meta_pixel_id'])->update(['setting_value' => '']);
        \App\Support\SiteSettingsHelper::forget('gtm_id');

        Livewire::test(AddToCart::class, ['productId' => $p->id, 'mode' => 'detail'])
            ->call('selectVariant', $big->id)
            ->assertNotDispatched('gtm:view_item');
    }

    public function test_order_now_counts_as_add_to_cart_with_the_variant_in_the_name(): void
    {
        $p = $this->honey();

        Livewire::test(AddToCart::class, ['productId' => $p->id, 'mode' => 'detail'])
            ->call('buyNow')
            ->assertDispatched('gtm:add_to_cart', fn ($event, $params) => $params['ecommerce']['value'] == 1300
                && $params['ecommerce']['items'][0]['item_name'] === 'সুন্দরবন মধু (৫০০g)'
                && $params['ecommerce']['content_name'] === 'সুন্দরবন মধু')
            ->assertRedirect(route('checkout'));

        Livewire::test(AddToCart::class, ['productId' => $p->id, 'mode' => 'detail'])
            ->call('add')
            ->assertDispatched('gtm:add_to_cart', fn ($event, $params) => $params['ecommerce']['items'][0]['item_variant'] === '৫০০g');
    }

    // ── InitiateCheckout once per cart ───────────────────────────────────

    public function test_begin_checkout_is_sent_once_per_cart_and_again_for_a_changed_cart_or_a_new_order(): void
    {
        $p = $this->honey();
        $small = $p->variants->firstWhere('label', '৫০০g');
        app(CartService::class)->add($p->id, 1, $small->id);

        Livewire::test(CheckoutForm::class)->assertDispatched('gtm:begin_checkout');
        Livewire::test(CheckoutForm::class)->assertNotDispatched('gtm:begin_checkout'); // reopened / reloaded

        // A different cart counts again.
        app(CartService::class)->add($p->id, 1, $small->id);
        Livewire::test(CheckoutForm::class)->assertDispatched('gtm:begin_checkout');
        Livewire::test(CheckoutForm::class)->assertNotDispatched('gtm:begin_checkout');

        // After an order is placed, the same items in a new cart count as a new checkout.
        $checkout = Livewire::test(CheckoutForm::class)
            ->set('customer_name', 'রহিম')->set('phone', '01712345678')->set('area', 'dhaka')->set('thana', 'মিরপুর')->set('address', 'বাসা ১')
            ->call('placeOrder');
        $checkout->assertDispatched('gtm:order_placed');

        app(CartService::class)->add($p->id, 2, $small->id);
        Livewire::test(CheckoutForm::class)->assertDispatched('gtm:begin_checkout');
    }

    // ── Order placed (named in the admin) ────────────────────────────────

    public function test_the_order_placed_event_goes_out_at_checkout_and_on_landing_pages_with_the_admins_name(): void
    {
        $p = $this->honey();
        $small = $p->variants->firstWhere('label', '৫০০g');
        app(CartService::class)->add($p->id, 1, $small->id);

        // Default name.
        $c = Livewire::test(CheckoutForm::class)
            ->set('customer_name', 'রহিম')->set('phone', '01712345678')->set('area', 'dhaka')->set('thana', 'মিরপুর')->set('address', 'বাসা ১')
            ->call('placeOrder');
        $order = Order::latest('id')->first();
        $c->assertDispatched('gtm:order_placed', fn ($e, $params) => $params['name'] === 'OrderPlaced'
            && $params['event_id'] === 'placed-'.$order->invoice_no
            && (float) $params['ecommerce']['value'] === (float) $order->total
            && $params['ecommerce']['currency'] === 'BDT');
        $this->assertSame('OrderPlaced', Tracking::orderEventName());

        // Renamed in Admin → GTM / Pixel.
        $this->setting('tracking_order_event', 'Lead_Order');
        $product = $this->product();
        $lp = LandingPage::create([
            'title' => 'LP', 'slug' => 'lp-x', 'template' => 'green', 'headline' => 'LP', 'product_id' => $product->id, 'is_active' => true,
            'blocks' => [['type' => 'order_form', 'data' => ['hidden' => false]]],
            'packages' => [['product_id' => $product->id, 'label' => '১ পিস', 'price' => 480, 'quantity' => 1]],
        ]);
        Livewire::test(LandingOrderForm::class, ['landingPage' => $lp])
            ->set('customer_name', 'করিম')->set('phone', '01812345678')->set('address', 'ঢাকা, মিরপুর')
            ->call('placeOrder')
            ->assertDispatched('gtm:order_placed', fn ($e, $params) => $params['name'] === 'Lead_Order');
    }

    public function test_no_order_placed_event_without_tracking(): void
    {
        SiteSetting::whereIn('setting_key', ['gtm_id', 'meta_pixel_id'])->update(['setting_value' => '']);
        \App\Support\SiteSettingsHelper::forget('gtm_id');

        $p = $this->honey();
        app(CartService::class)->add($p->id, 1, $p->variants->first()->id);

        Livewire::test(CheckoutForm::class)
            ->set('customer_name', 'রহিম')->set('phone', '01712345678')->set('area', 'dhaka')->set('thana', 'মিরপুর')->set('address', 'বাসা ১')
            ->call('placeOrder')
            ->assertNotDispatched('gtm:order_placed');
    }

    // ── Search / category / registration ─────────────────────────────────

    public function test_search_and_category_pages_send_their_events(): void
    {
        $p = $this->honey();

        $html = $this->get('/shop?q='.urlencode('মধু'))->assertOk()->getContent();
        $this->assertStringContainsString("trackEvent('search'", $html);
        $this->assertStringContainsString('"search_term":'.json_encode('মধু'), $html);
        $this->assertStringNotContainsString("trackEvent('view_category'", $html);

        $html = $this->get('/shop?category=honey')->assertOk()->getContent();
        $this->assertStringContainsString("trackEvent('view_category'", $html);
        $this->assertStringContainsString('"category_slug":"honey"', $html);
        $this->assertStringContainsString('"content_ids":["'.$p->id.'"]', $html);

        $html = $this->get('/shop')->assertOk()->getContent();
        $this->assertStringNotContainsString("trackEvent('search'", $html);
        $this->assertStringNotContainsString("trackEvent('view_category'", $html);
    }

    public function test_a_new_customer_account_sends_complete_registration_once(): void
    {
        $this->post('/customer/register', ['name' => 'নতুন', 'phone' => '01712345678', 'password' => 'secret12'])->assertRedirect();

        $this->get('/')->assertOk()->assertSee("trackEvent('complete_registration'", false);
        $this->get('/')->assertOk()->assertDontSee("trackEvent('complete_registration'", false);
    }

    // ── GTM ID rule and the order event name in the admin ────────────────

    public function test_a_wrong_gtm_id_does_not_save_and_the_event_name_is_checked(): void
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }

        $this->actingAs(AdminUser::create(['username' => 'boss', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');

        foreach (['GTM123', 'GTM-', 'GTM-AB', 'GTM-ABC 123', 'G-ABC1234', 'GTM-ABCDEFGHIJK', '<script>', 'GTM-ABC_123'] as $bad) {
            Livewire::test(GtmSettings::class)->set('data.gtm_id', $bad)->call('save')->assertHasErrors(['data.gtm_id']);
        }
        $this->assertSame('GTM-TEST123', SiteSetting::where('setting_key', 'gtm_id')->value('setting_value'), 'nothing was saved');

        // Lower case is accepted and stored in capitals; empty switches it off.
        Livewire::test(GtmSettings::class)->set('data.gtm_id', ' gtm-abc1234 ')->call('save')->assertHasNoErrors();
        $this->assertSame('GTM-ABC1234', SiteSetting::where('setting_key', 'gtm_id')->value('setting_value'));
        Livewire::test(GtmSettings::class)->set('data.gtm_id', '')->call('save')->assertHasNoErrors();
        $this->assertSame('', SiteSetting::where('setting_key', 'gtm_id')->value('setting_value'));

        foreach (['1Order', 'Order Placed', 'Order-Placed', 'x'.str_repeat('a', 40)] as $bad) {
            Livewire::test(GtmSettings::class)->set('data.tracking_order_event', $bad)->call('save')->assertHasErrors(['data.tracking_order_event']);
        }

        Livewire::test(GtmSettings::class)->set('data.tracking_order_event', 'Order_Done')->call('save')->assertHasNoErrors();
        $this->assertSame('Order_Done', Tracking::orderEventName());
        Livewire::test(GtmSettings::class)->set('data.tracking_order_event', 'OrderPlaced')->call('save');
        $this->assertSame('OrderPlaced', Tracking::orderEventName());
        $this->assertSame('', SiteSetting::where('setting_key', 'tracking_order_event')->value('setting_value'), 'the default is stored empty');
    }

    // ── The front-end script ─────────────────────────────────────────────

    public function test_the_head_turns_tracking_on_for_people_and_the_script_has_the_interest_steps_and_the_ecommerce_clear(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('window.__trackingOn = !window.__trackingBlocked', $html);
        $this->assertStringContainsString('navigator.webdriver', $html);
        $this->assertStringContainsString('ecommerce: null', $html);

        $js = file_get_contents(resource_path('js/gtm.js'));
        $this->assertStringContainsString('[10, 30, 60, 120]', $js);
        $this->assertStringContainsString('[25, 50, 75, 100]', $js);
        $this->assertStringContainsString("document.visibilityState !== 'visible'", $js);
        $this->assertStringContainsString('ecommerce: null', $js);
    }

    // ── What an order keeps for a later server-side Purchase ─────────────

    public function test_the_order_keeps_the_pixel_cookies_user_agent_page_and_ga_client_id(): void
    {
        $p = $this->honey();
        $request = Request::create('/livewire/update', 'POST', [], [
            '_fbp' => 'fb.1.1700000000000.123456789',
            '_fbc' => 'fb.1.1700000000001.AbCdEf',
            '_ga' => 'GA1.1.1234567890.1700000000',
        ], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 14) Chrome/120 Mobile',
            'HTTP_REFERER' => 'https://shop.test/product/honey-1?utm_source=fb',
            'REMOTE_ADDR' => '45.67.89.10',
        ]);
        $this->app->instance('request', $request);

        $order = app(OrderService::class)->createOrder(
            [['product_id' => $p->id, 'variant_id' => $p->variants->first()->id, 'quantity' => 1]],
            ['customer_name' => 'রহিম', 'phone' => '01712345678', 'district' => 'ঢাকা', 'thana' => 'x', 'address' => 'a', 'payment_method' => 'cod', 'ip_address' => $request->ip()],
            null, null, true, true,
        )->fresh();

        $this->assertSame('fb.1.1700000000000.123456789', $order->fbp);
        $this->assertSame('fb.1.1700000000001.AbCdEf', $order->fbc);
        $this->assertSame('1234567890.1700000000', $order->ga_client_id);
        $this->assertSame('Mozilla/5.0 (Linux; Android 14) Chrome/120 Mobile', $order->client_user_agent);
        $this->assertSame('https://shop.test/product/honey-1?utm_source=fb', $order->event_source_url);
        $this->assertSame('45.67.89.10', $order->ip_address);
    }

    public function test_fbc_is_built_from_the_click_id_when_the_cookie_is_missing_and_nothing_breaks_without_cookies(): void
    {
        $ctx = Tracking::requestContext(Request::create('/livewire/update', 'POST', [], [], [], [
            'HTTP_REFERER' => 'https://shop.test/lp/x?fbclid=IwAR0abc',
        ]));
        $this->assertMatchesRegularExpression('/^fb\.1\.\d{13}\.IwAR0abc$/', $ctx['fbc']);
        $this->assertNull($ctx['fbp']);
        $this->assertNull($ctx['ga_client_id']);

        $empty = Tracking::requestContext(Request::create('/x'));
        $this->assertNull($empty['fbp']);
        $this->assertNull($empty['fbc']);
        $this->assertSame('http://localhost/x', $empty['event_source_url']);

        $this->assertNull(Tracking::requestContext(Request::create('/', 'GET', [], ['_ga' => 'garbage']))['ga_client_id']);
    }

    public function test_the_pixel_and_analytics_cookies_reach_the_server_unencrypted(): void
    {
        Route::middleware('web')->get('/_cookie-probe', fn () => response()->json(['fbp' => request()->cookie('_fbp'), 'ga' => request()->cookie('_ga')]));

        $this->withUnencryptedCookies(['_fbp' => 'fb.1.1.2', '_ga' => 'GA1.1.11.22'])
            ->get('/_cookie-probe')
            ->assertOk()
            ->assertJson(['fbp' => 'fb.1.1.2', 'ga' => 'GA1.1.11.22']);
    }

    // ── Bots get no tracking ─────────────────────────────────────────────

    public function test_bots_get_no_tracking_and_people_do(): void
    {
        foreach ([
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
            'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
            'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/120.0 Safari/537.36',
            'curl/8.4.0', 'python-requests/2.31', 'Mozilla/5.0 (compatible; AhrefsBot/7.0)', 'Lighthouse', 'WhatsApp/2.23',
        ] as $ua) {
            $html = $this->withHeader('User-Agent', $ua)->get('/')->assertOk()->getContent();
            $this->assertStringNotContainsString('googletagmanager.com', $html, $ua);
            $this->assertStringNotContainsString('fbevents.js', $html, $ua);
            $this->assertStringNotContainsString('__trackingOn', $html, $ua);
            $this->assertTrue(Tracking::isBot(Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => $ua])), $ua);
        }

        foreach ([
            'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 [FB_IAB/FB4A;FBAV/430.0]',
        ] as $ua) {
            $html = $this->withHeader('User-Agent', $ua)->get('/')->assertOk()->getContent();
            $this->assertStringContainsString('GTM-TEST123', $html, $ua);
            $this->assertStringContainsString("fbq('init', '123456789012345')", $html, $ua);
        }
    }
}
