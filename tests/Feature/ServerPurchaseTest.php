<?php

namespace Tests\Feature;

use App\Filament\Pages\GtmSettings;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Jobs\SendServerPurchase;
use App\Models\AdminUser;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Services\Tracking\ServerPurchase;
use App\Support\TrackingSettings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/**
 * Tracking part 2: Purchase from the server (Meta Conversions API + GA4 Measurement Protocol) when an order is
 * confirmed — the settings and their secrets, when it triggers, what is sent, once-only, retries, the order page,
 * "send again" and "Test connection".
 */
class ServerPurchaseTest extends TestCase
{
    use CreatesShopData;

    private const TOKEN = 'EAAB-super-secret-token-9f3a';

    private const SECRET = 'ga4-api-secret-xyz-7731';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setting('meta_pixel_id', '123456789012345');
        TrackingSettings::putSecret(TrackingSettings::CAPI_TOKEN, self::TOKEN);
        $this->setting(TrackingSettings::GA4_ID, 'G-ABC123XYZ9');
        TrackingSettings::putSecret(TrackingSettings::GA4_SECRET, self::SECRET);
    }

    private function okFakes(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['events_received' => 1, 'messages' => [], 'fbtrace_id' => 'TRACE1'], 200),
            'www.google-analytics.com/mp/collect*' => Http::response('', 204),
            'www.google-analytics.com/debug/mp/collect*' => Http::response(['validationMessages' => []], 200),
        ]);
    }

    /** An order with everything the browser saved at order time. */
    private function order(array $extra = []): Order
    {
        $order = $this->placeOrder(customer: ['customer_name' => 'Rahim Uddin', 'phone' => '01712345678']);
        $order->forceFill($extra + [
            'fbp' => 'fb.1.1700000000000.111', 'fbc' => 'fb.1.1700000000001.AbC', 'ip_address' => '45.67.89.10',
            'client_user_agent' => 'Mozilla/5.0 Test', 'event_source_url' => 'https://shop.test/product/x',
            'ga_client_id' => '1234567890.1700000000', 'district' => 'ঢাকা', 'email' => 'Rahim@Example.com',
        ])->saveQuietly();

        return $order->fresh();
    }

    private function runJob(Order $order, bool $force = false): void
    {
        // The job only sends orders that reached Confirmed; put the test order there without the observer.
        Order::whereKey($order->id)->where('status', 'pending')->update(['status' => 'processing']);

        (new SendServerPurchase($order->id, $force))->handle(app(ServerPurchase::class));
    }

    // ── the settings and their secrets ───────────────────────────────────

    public function test_secrets_are_stored_encrypted_and_only_the_last_four_characters_are_ever_shown(): void
    {
        $raw = SiteSetting::where('setting_key', TrackingSettings::CAPI_TOKEN)->value('setting_value');

        $this->assertStringNotContainsString(self::TOKEN, $raw);
        $this->assertSame(self::TOKEN, Crypt::decryptString($raw));
        $this->assertSame(self::TOKEN, TrackingSettings::capiToken());
        $this->assertSame('••••••••9f3a', TrackingSettings::masked(TrackingSettings::CAPI_TOKEN));
        $this->assertSame('••••••••7731', TrackingSettings::masked(TrackingSettings::GA4_SECRET));

        // A damaged value or a changed APP_KEY reads as "not set", never as an error.
        SiteSetting::where('setting_key', TrackingSettings::CAPI_TOKEN)->update(['setting_value' => 'garbage']);
        \App\Support\SiteSettingsHelper::forget(); // a bulk update bypasses the model, so drop the settings cache by hand
        $this->assertNull(TrackingSettings::capiToken());
        $this->assertFalse(TrackingSettings::capiReady());
    }

    public function test_the_admin_page_never_shows_a_saved_secret_and_saving_keeps_replaces_or_removes_it(): void
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }

        $this->actingAs(AdminUser::create(['username' => 'boss', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');
        $this->setting('admin_locale', 'en');
        app()->setLocale('en');

        $page = Livewire::test(GtmSettings::class)
            ->assertSet('data.capi_access_token', null)
            ->assertSet('data.ga4_api_secret', null)
            ->assertSee('••••••••9f3a')
            ->assertSee('••••••••7731');
        $this->assertStringNotContainsString(self::TOKEN, $page->html());
        $this->assertStringNotContainsString(self::SECRET, $page->html());
        $this->assertStringNotContainsString('9f3a-', $page->html());

        // Empty = keep.
        $page->call('save')->assertHasNoErrors();
        $this->assertSame(self::TOKEN, TrackingSettings::capiToken());
        $this->assertSame(self::SECRET, TrackingSettings::ga4Secret());

        // A new one replaces it — and is gone from the page afterwards.
        $page->set('data.capi_access_token', 'EAAB-brand-new-token-0042')->call('save')->assertHasNoErrors()
            ->assertSet('data.capi_access_token', null);
        $this->assertSame('EAAB-brand-new-token-0042', TrackingSettings::capiToken());
        $this->assertStringNotContainsString('brand-new-token', $page->html());
        $this->assertSame('••••••••0042', TrackingSettings::masked(TrackingSettings::CAPI_TOKEN));

        // The remove switch deletes it.
        $page->set('data.ga4_api_secret_clear', true)->call('save');
        $this->assertNull(TrackingSettings::ga4Secret());
        $this->assertNotNull(TrackingSettings::capiToken());

        // A secret with a space in it is refused.
        $page->set('data.capi_access_token', 'has a space')->call('save')->assertHasErrors(['data.capi_access_token']);
    }

    public function test_the_ga4_id_the_status_and_the_event_name_are_checked(): void
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }

        $this->actingAs(AdminUser::create(['username' => 'boss', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');

        foreach (['GA-123', 'G-', 'G-AB', 'G-ABC 123', 'UA-12345-1', 'G-ABC123XYZ9ABCD', 'GTM-ABC1234'] as $bad) {
            Livewire::test(GtmSettings::class)->set('data.ga4_measurement_id', $bad)->call('save')->assertHasErrors(['data.ga4_measurement_id']);
        }

        Livewire::test(GtmSettings::class)->set('data.ga4_measurement_id', ' g-abc999xyz1 ')->call('save')->assertHasNoErrors();
        $this->assertSame('G-ABC999XYZ1', TrackingSettings::ga4Id());

        // "Purchase" as the browser event's name would double every sale.
        Livewire::test(GtmSettings::class)->set('data.tracking_order_event', 'purchase')->call('save')->assertHasErrors(['data.tracking_order_event']);

        // The status that sends Purchase: Confirmed unless changed.
        $this->assertSame('processing', TrackingSettings::purchaseStatus());
        Livewire::test(GtmSettings::class)->set('data.tracking_purchase_status', 'shipped')->call('save')->assertHasNoErrors();
        $this->assertSame('shipped', TrackingSettings::purchaseStatus());
        Livewire::test(GtmSettings::class)->set('data.tracking_purchase_status', 'processing')->call('save');
        $this->assertSame('', SiteSetting::where('setting_key', TrackingSettings::PURCHASE_STATUS)->value('setting_value'));
    }

    public function test_the_save_button_is_translated(): void
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }

        $this->actingAs(AdminUser::create(['username' => 'boss', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');
        $this->setting('admin_locale', 'en');
        app()->setLocale('en');

        Livewire::test(GtmSettings::class)->assertSee('Save')->assertDontSee('সেভ করুন');
        $this->get('/admin/site-colors')->assertOk()->assertSee('Save')->assertDontSee('সেভ করুন');
    }

    // ── GA4 straight on the site, or through GTM ─────────────────────────

    public function test_ga4_loads_straight_only_without_a_gtm_container(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('googletagmanager.com/gtag/js?id=G-ABC123XYZ9', $html);
        $this->assertStringContainsString("gtag('config', 'G-ABC123XYZ9')", $html);
        $this->assertStringContainsString('window.__trackingOn', $html);

        // With a GTM container GA4 belongs inside GTM — loading it here too would double everything.
        $this->setting('gtm_id', 'GTM-TEST123');
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('GTM-TEST123', $html);
        $this->assertStringNotContainsString('gtag/js', $html);
        $this->assertStringNotContainsString("gtag('config'", $html);

        // Bots get neither.
        $this->setting('gtm_id', '');
        $bot = $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('gtag/js', $bot);
    }

    public function test_the_admin_warns_when_both_a_gtm_container_and_ga4_are_set(): void
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }

        $this->actingAs(AdminUser::create(['username' => 'boss', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');
        $this->setting('admin_locale', 'en');
        app()->setLocale('en');

        Livewire::test(GtmSettings::class)->assertDontSee('will NOT be loaded straight');

        Livewire::test(GtmSettings::class)
            ->set('data.gtm_id', 'GTM-ABC1234')
            ->assertSee('will NOT be loaded straight on the site');
    }

    public function test_the_script_hands_events_to_gtag_when_ga4_is_loaded_straight(): void
    {
        $js = file_get_contents(resource_path('js/gtm.js'));

        $this->assertStringContainsString('window.__ga4Direct === true', $js);
        $this->assertStringContainsString("complete_registration: 'sign_up'", $js);
        $this->assertStringContainsString('sendGtag(', $js);
    }

    // ── when it is sent ──────────────────────────────────────────────────

    public function test_it_goes_out_once_when_the_order_reaches_confirmed_and_not_before_or_again(): void
    {
        Queue::fake();

        $order = $this->order();
        Queue::assertNothingPushed(); // pending

        $order->update(['status' => 'processing']);
        Queue::assertPushed(SendServerPurchase::class, 1);
        $this->assertNotNull($order->fresh()->purchase_triggered_at);

        // Later statuses, or flipping back and forth, never queue it again.
        $order->update(['status' => 'shipped']);
        $order->update(['status' => 'processing']);
        $order->update(['status' => 'delivered']);
        Queue::assertPushed(SendServerPurchase::class, 1);
    }

    public function test_skipping_confirmed_straight_to_shipped_still_sends(): void
    {
        Queue::fake();
        $order = $this->order();

        $order->update(['status' => 'shipped']);

        Queue::assertPushed(SendServerPurchase::class, 1);
    }

    public function test_cancelled_on_hold_and_unpaid_bkash_orders_never_send(): void
    {
        Queue::fake();

        foreach (['cancelled', 'on_hold'] as $status) {
            $this->order()->update(['status' => $status]);
        }

        $bkash = $this->order();
        $bkash->forceFill(['payment_method' => 'bkash', 'payment_status' => 'unpaid'])->saveQuietly();
        $bkash->update(['status' => 'processing']);
        Queue::assertNothingPushed();

        // Once the bKash payment is in, it does.
        $bkash->update(['payment_status' => 'paid']);
        Queue::assertPushed(SendServerPurchase::class, 1);
    }

    public function test_the_status_is_chosen_in_the_admin(): void
    {
        Queue::fake();

        $this->setting(TrackingSettings::PURCHASE_STATUS, 'shipped');
        $order = $this->order();
        $order->update(['status' => 'processing']);
        Queue::assertNothingPushed();
        $order->update(['status' => 'shipped']);
        Queue::assertPushed(SendServerPurchase::class, 1);

        // "pending": it goes the moment the order is placed.
        $this->setting(TrackingSettings::PURCHASE_STATUS, 'pending');
        $this->order();
        Queue::assertPushed(SendServerPurchase::class, 2);
    }

    public function test_nothing_is_queued_when_neither_platform_is_set_up(): void
    {
        Queue::fake();
        SiteSetting::where('setting_key', TrackingSettings::CAPI_TOKEN)->update(['setting_value' => '']);
        \App\Support\SiteSettingsHelper::forget(); // a bulk update bypasses the model, so drop the settings cache by hand
        SiteSetting::where('setting_key', TrackingSettings::GA4_SECRET)->update(['setting_value' => '']);
        \App\Support\SiteSettingsHelper::forget(); // a bulk update bypasses the model, so drop the settings cache by hand

        $this->order()->update(['status' => 'processing']);

        Queue::assertNothingPushed();
        $this->assertFalse(ServerPurchase::configured());
    }

    // ── what is sent ─────────────────────────────────────────────────────

    public function test_meta_gets_the_event_with_hashed_matching_data_and_the_saved_browser_data(): void
    {
        $this->okFakes();
        $this->setting(TrackingSettings::CAPI_TEST_CODE, 'TEST777');
        $order = $this->order(['purchase_triggered_at' => now()]);

        $this->runJob($order);

        Http::assertSent(function (HttpRequest $request) use ($order) {
            if (! str_starts_with($request->url(), 'https://graph.facebook.com/')) {
                return false;
            }

            $body = $request->data();
            $event = $body['data'][0];

            $this->assertStringContainsString('/123456789012345/events', $request->url());
            $this->assertStringNotContainsString(self::TOKEN, $request->url(), 'the token is in the body, not the URL');
            $this->assertSame(self::TOKEN, $body['access_token']);
            $this->assertSame('TEST777', $body['test_event_code']);
            $this->assertSame('Purchase', $event['event_name']);
            $this->assertSame('purchase-'.$order->invoice_no, $event['event_id']);
            $this->assertSame('website', $event['action_source']);
            $this->assertSame('https://shop.test/product/x', $event['event_source_url']);
            $this->assertSame($order->purchase_triggered_at->timestamp, $event['event_time']);

            $user = $event['user_data'];
            $this->assertSame('45.67.89.10', $user['client_ip_address']);
            $this->assertSame('Mozilla/5.0 Test', $user['client_user_agent']);
            $this->assertSame('fb.1.1700000000000.111', $user['fbp']);
            $this->assertSame('fb.1.1700000000001.AbC', $user['fbc']);
            $this->assertSame([hash('sha256', '8801712345678')], $user['ph']);
            $this->assertSame([hash('sha256', 'rahim')], $user['fn']);
            $this->assertSame([hash('sha256', 'uddin')], $user['ln']);
            $this->assertSame([hash('sha256', 'ঢাকা')], $user['ct']);
            $this->assertSame([hash('sha256', 'rahim@example.com')], $user['em']);
            $this->assertSame([hash('sha256', 'bd')], $user['country']);

            // The plain phone, name and city never leave the shop.
            $json = json_encode($body, JSON_UNESCAPED_UNICODE);
            foreach (['01712345678', '8801712345678', 'Rahim', 'Uddin', 'ঢাকা', 'Rahim@Example.com'] as $plain) {
                $this->assertStringNotContainsString($plain, $json, $plain);
            }

            $custom = $event['custom_data'];
            $this->assertSame('BDT', $custom['currency']);
            $this->assertEquals((float) $order->total, $custom['value']);
            $this->assertSame($order->invoice_no, $custom['order_id']);
            $this->assertSame([(string) $order->items->first()->product_id], $custom['content_ids']);

            return true;
        });

        $this->assertSame('sent', $order->fresh()->capi_status);
        $this->assertNull($order->fresh()->capi_error);
        $this->assertNotNull($order->fresh()->capi_sent_at);
    }

    public function test_ga4_gets_the_purchase_with_the_client_id_and_hashed_user_data(): void
    {
        $this->okFakes();
        $order = $this->order(['purchase_triggered_at' => now()]);

        $this->runJob($order);

        Http::assertSent(function (HttpRequest $request) use ($order) {
            if (! str_starts_with($request->url(), 'https://www.google-analytics.com/mp/collect')) {
                return false;
            }

            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);
            $this->assertSame('G-ABC123XYZ9', $query['measurement_id']);
            $this->assertSame(self::SECRET, $query['api_secret']);

            $body = $request->data();
            $this->assertSame('1234567890.1700000000', $body['client_id']);
            $this->assertSame('purchase', $body['events'][0]['name']);

            $params = $body['events'][0]['params'];
            $this->assertSame($order->invoice_no, $params['transaction_id']);
            $this->assertSame('BDT', $params['currency']);
            $this->assertEquals((float) $order->total, $params['value']);
            $this->assertEquals((float) $order->shipping_fee, $params['shipping']);
            $this->assertSame($order->items->first()->product_name, $params['items'][0]['item_name']);

            $this->assertSame([hash('sha256', '+8801712345678')], $body['user_data']['sha256_phone_number']);
            $this->assertSame(hash('sha256', 'rahim'), $body['user_data']['address'][0]['sha256_first_name']);
            $this->assertStringNotContainsString('01712345678', json_encode($body));

            return true;
        });

        $this->assertSame('sent', $order->fresh()->ga4_status);
    }

    public function test_ga4_has_a_steady_client_id_when_the_browser_gave_none(): void
    {
        $order = $this->order(['ga_client_id' => null]);

        $a = app(ServerPurchase::class)->ga4Body($order)['client_id'];
        $b = app(ServerPurchase::class)->ga4Body($order)['client_id'];

        $this->assertSame($a, $b);
        $this->assertMatchesRegularExpression('/^\d+\.\d+$/', $a);
    }

    public function test_phone_numbers_are_sent_with_the_country_code(): void
    {
        $this->assertSame('8801712345678', ServerPurchase::phoneDigits('01712345678'));
        $this->assertSame('8801712345678', ServerPurchase::phoneDigits('+880 1712-345678'));
        $this->assertSame('8801712345678', ServerPurchase::phoneDigits('8801712345678'));
    }

    // ── once, retries, failures ──────────────────────────────────────────

    public function test_each_platform_is_sent_once_even_if_the_job_runs_again(): void
    {
        $this->okFakes();
        $order = $this->order();

        $this->runJob($order);
        $this->runJob($order);
        $this->runJob($order, force: true); // even "send again" skips what already went out

        Http::assertSentCount(2); // one to Meta, one to GA4
    }

    public function test_a_rejected_token_fails_that_platform_with_a_reason_and_is_not_retried_while_the_other_still_goes(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token.', 'code' => 190]], 400),
            'www.google-analytics.com/mp/collect*' => Http::response('', 204),
        ]);
        $order = $this->order();

        $this->runJob($order); // no exception: a rejected token is not worth retrying

        $order = $order->fresh();
        $this->assertSame('failed', $order->capi_status);
        $this->assertStringContainsString('Invalid OAuth access token.', $order->capi_error);
        $this->assertNull($order->capi_sent_at);
        $this->assertSame('sent', $order->ga4_status);
    }

    public function test_server_errors_are_retried_and_a_later_success_clears_the_failure(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()->push('oops', 503)->push(['events_received' => 1, 'fbtrace_id' => 'T'], 200),
            'www.google-analytics.com/mp/collect*' => Http::response('', 204),
        ]);
        $order = $this->order();

        try {
            $this->runJob($order);
            $this->fail('A server error must make the job retry.');
        } catch (\RuntimeException) {
            $this->assertSame('failed', $order->fresh()->capi_status);
            $this->assertSame('sent', $order->fresh()->ga4_status, 'GA4 is not held back by Meta');
        }

        $this->runJob($order);

        $this->assertSame('sent', $order->fresh()->capi_status);
        $this->assertNull($order->fresh()->capi_error);
        $this->assertSame(['tries' => 4, 'backoff' => [60, 300, 900]], ['tries' => (new SendServerPurchase(1))->tries, 'backoff' => (new SendServerPurchase(1))->backoff]);
    }

    public function test_no_token_or_secret_ever_ends_up_in_a_stored_error(): void
    {
        Http::fake([
            // An API that echoes the token back, and a network failure that mentions the full URL with the secret.
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Bad token '.self::TOKEN.' used']], 400),
            'www.google-analytics.com/mp/collect*' => fn () => throw new ConnectionException('cURL error 28 for https://www.google-analytics.com/mp/collect?measurement_id=G-ABC123XYZ9&api_secret='.self::SECRET),
        ]);
        $order = $this->order();

        try {
            $this->runJob($order);
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString(self::SECRET, $e->getMessage());
        }

        $order = $order->fresh();
        foreach ([$order->capi_error, $order->ga4_error] as $error) {
            $this->assertNotEmpty($error);
            $this->assertStringNotContainsString(self::TOKEN, $error);
            $this->assertStringNotContainsString(self::SECRET, $error);
        }
        $this->assertStringContainsString('[hidden]', $order->capi_error);
        $this->assertStringContainsString('api_secret=[hidden]', $order->ga4_error);

        $this->assertSame('x=[hidden]', str_replace('x=zzzzzz', 'x=[hidden]', 'x=zzzzzz'));
        $this->assertStringNotContainsString('abcdef123456', ServerPurchase::sanitize('https://x.test/?access_token=abcdef123456&y=1'));
    }

    // ── the order page and "send again" ──────────────────────────────────

    private function asAdmin(): void
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }

        $this->setting('admin_locale', 'en');
        app()->setLocale('en');
        $this->actingAs(AdminUser::create(['username' => 'boss', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');
    }

    public function test_the_order_page_shows_each_platform_separately_with_the_reason_for_a_failure(): void
    {
        $this->asAdmin();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token.']], 400),
            'www.google-analytics.com/mp/collect*' => Http::response('', 204),
        ]);
        $order = $this->order();

        // Before it qualifies.
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertSee('Meta (Conversions API): Not sent yet')
            ->assertSee('Google Analytics 4: Not sent yet');

        $order->update(['status' => 'processing']); // queue is sync in tests: it goes now

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertSee('Meta (Conversions API): ✖ Failed — Meta: Invalid OAuth access token.')
            ->assertSee('Google Analytics 4: ✔ Sent');
    }

    public function test_send_again_resends_only_what_has_not_gone_out(): void
    {
        $this->asAdmin();
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()->push(['error' => ['message' => 'Temporarily broken']], 400)->push(['events_received' => 1, 'fbtrace_id' => 'T'], 200),
            'www.google-analytics.com/mp/collect*' => Http::response('', 204),
        ]);
        $order = $this->order();
        $order->update(['status' => 'processing']);
        $this->assertSame('failed', $order->fresh()->capi_status);
        $this->assertSame('sent', $order->fresh()->ga4_status);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('resendPurchase')
            ->callAction('resendPurchase')
            ->assertNotified();

        $order = $order->fresh();
        $this->assertSame('sent', $order->capi_status);
        Http::assertSentCount(3); // Meta twice (failed, then sent), GA4 once — GA4 was not sent again

        // Everything went out: the button goes away.
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])->assertActionHidden('resendPurchase');
    }

    public function test_send_again_is_not_offered_for_cancelled_orders_or_without_a_setup(): void
    {
        $this->asAdmin();
        Http::fake();

        $cancelled = $this->order();
        $cancelled->update(['status' => 'cancelled']);
        Livewire::test(EditOrder::class, ['record' => $cancelled->getRouteKey()])->assertActionHidden('resendPurchase');

        $plain = $this->order();
        SiteSetting::where('setting_key', TrackingSettings::CAPI_TOKEN)->update(['setting_value' => '']);
        \App\Support\SiteSettingsHelper::forget(); // a bulk update bypasses the model, so drop the settings cache by hand
        SiteSetting::where('setting_key', TrackingSettings::GA4_SECRET)->update(['setting_value' => '']);
        \App\Support\SiteSettingsHelper::forget(); // a bulk update bypasses the model, so drop the settings cache by hand
        Livewire::test(EditOrder::class, ['record' => $plain->getRouteKey()])->assertActionHidden('resendPurchase');
    }

    // ── Test connection ──────────────────────────────────────────────────

    public function test_the_meta_test_needs_a_test_event_code_and_sends_only_with_it(): void
    {
        $this->okFakes();
        $service = app(ServerPurchase::class);

        $refused = $service->testMeta('123456789012345', self::TOKEN, null);
        $this->assertFalse($refused['ok']);
        $this->assertStringContainsString('Test Event Code', $refused['message']);
        Http::assertNothingSent(); // without a code it would count as a real purchase

        $result = $service->testMeta('123456789012345', self::TOKEN, 'TEST777');
        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('events_received: 1', $result['message']);
        $this->assertStringContainsString('TEST777', $result['message']);

        Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->url(), 'https://graph.facebook.com/')
            && $r->data()['test_event_code'] === 'TEST777'
            && str_starts_with($r->data()['data'][0]['event_id'], 'test-')
            && ! array_key_exists('em', $r->data()['data'][0]['user_data']));
    }

    public function test_the_meta_test_reports_a_bad_token_without_showing_it(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Error validating access token with '.self::TOKEN]], 400)]);

        $result = app(ServerPurchase::class)->testMeta('123456789012345', self::TOKEN, 'TEST777');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Error validating access token', $result['message']);
        $this->assertStringNotContainsString(self::TOKEN, $result['message']);
    }

    public function test_the_ga4_test_uses_the_validator_and_reports_its_complaints(): void
    {
        Http::fake(['www.google-analytics.com/debug/mp/collect*' => Http::sequence()
            ->push(['validationMessages' => []], 200)
            ->push(['validationMessages' => [['description' => 'The value provided for the event param must be numeric.', 'validationCode' => 'VALUE_INVALID']]], 200)]);
        $service = app(ServerPurchase::class);

        $ok = $service->testGa4('G-ABC123XYZ9', self::SECRET);
        $this->assertTrue($ok['ok']);

        $bad = $service->testGa4('G-ABC123XYZ9', self::SECRET);
        $this->assertFalse($bad['ok']);
        $this->assertStringContainsString('must be numeric', $bad['message']);

        // Only the validator is called — nothing is recorded in GA4.
        Http::assertSentCount(2);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/debug/mp/collect'));
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/mp/collect?') && ! str_contains($r->url(), '/debug/'));
    }

    public function test_the_test_buttons_on_the_admin_page_use_what_was_typed_and_show_the_result(): void
    {
        $this->asAdmin();
        $this->okFakes();

        // Meta: typed token + test code, shown as a notification; the typed token is not echoed.
        Livewire::test(GtmSettings::class)
            ->set('data.capi_access_token', 'EAAB-typed-token-5555')
            ->set('data.capi_test_event_code', 'TEST999')
            ->callAction(TestAction::make('testMeta')->schemaComponent('server'))
            ->assertNotified('Connection OK');

        Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->url(), 'https://graph.facebook.com/') && $r->data()['access_token'] === 'EAAB-typed-token-5555' && $r->data()['test_event_code'] === 'TEST999');

        // Without a code it refuses and sends nothing more.
        $before = count(Http::recorded());
        Livewire::test(GtmSettings::class)
            ->set('data.capi_test_event_code', '')
            ->callAction(TestAction::make('testMeta')->schemaComponent('server'))
            ->assertNotified('Connection failed');
        $this->assertCount($before, Http::recorded());

        Livewire::test(GtmSettings::class)
            ->callAction(TestAction::make('testGa4')->schemaComponent('ga4'))
            ->assertNotified('Connection OK');
    }
}
