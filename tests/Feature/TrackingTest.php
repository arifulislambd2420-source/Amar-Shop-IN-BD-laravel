<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use CreatesShopData;

    public function test_nothing_is_loaded_without_ids(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('googletagmanager.com', $html);
        $this->assertStringNotContainsString('fbevents.js', $html);
    }

    public function test_gtm_and_pixel_load_site_wide_when_configured(): void
    {
        $this->setting('gtm_id', 'GTM-TEST123');
        $this->setting('meta_pixel_id', '123456789012345');

        foreach (['/', '/shop', '/product/'.$this->product()->slug] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('GTM-TEST123', $html, $url);
            $this->assertStringContainsString("fbq('init', '123456789012345')", $html, $url);
            $this->assertStringContainsString("event: 'page_view'", $html, $url);
        }
    }

    public function test_kill_switch_disables_tracking(): void
    {
        config(['services.gtm.enabled' => false]);
        $this->setting('gtm_id', 'GTM-TEST123');
        $this->setting('meta_pixel_id', '123456789012345');

        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('GTM-TEST123', $html);
        $this->assertStringNotContainsString('fbevents.js', $html);
    }

    public function test_product_page_fires_view_item(): void
    {
        $this->setting('gtm_id', 'GTM-TEST123');
        $product = $this->product(['price' => 750]);

        $html = $this->get('/product/'.$product->slug)->getContent();

        $this->assertStringContainsString("trackEvent('view_item'", $html);
        $this->assertStringContainsString('"currency":"BDT"', $html);
        $this->assertStringContainsString('"value":750', $html);
    }

    public function test_the_browser_no_longer_sends_purchase_from_the_order_page(): void
    {
        $this->setting('gtm_id', 'GTM-TEST123');
        $this->setting('meta_pixel_id', '123456789012345');
        $order = $this->placeOrder();

        // Purchase is sent by the server when the order is confirmed (see ServerPurchaseTest); the page only tracks the view.
        foreach ([1, 2] as $visit) {
            $html = $this->get('/order/'.$order->order_token)->assertOk()->getContent();

            $this->assertStringNotContainsString("trackEvent('purchase'", $html);
            $this->assertStringNotContainsString('purchase-'.$order->invoice_no, $html);
            $this->assertStringContainsString('GTM-TEST123', $html);
        }
    }
    public function test_landing_page_loads_tracking_and_events(): void
    {
        $this->setting('gtm_id', 'GTM-TEST123');
        $this->setting('meta_pixel_id', '123456789012345');
        $product = $this->product();

        \App\Models\LandingPage::create([
            'title' => 'LP', 'slug' => 'lp-track', 'template' => 'green', 'headline' => 'LP',
            'product_id' => $product->id, 'is_active' => true,
            'blocks' => [['type' => 'order_form', 'data' => ['hidden' => false]]],
        ]);

        $html = $this->get('/lp/lp-track')->assertOk()->getContent();

        $this->assertStringContainsString('GTM-TEST123', $html);
        $this->assertStringContainsString("fbq('init', '123456789012345')", $html);
        $this->assertStringContainsString("trackEvent('view_item'", $html);
        $this->assertStringContainsString("trackEvent('begin_checkout'", $html);
    }
}
