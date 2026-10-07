<?php

namespace Tests\Feature;

use App\Support\Delivery;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

class DeliveryChargeTest extends TestCase
{
    use CreatesShopData;

    public function test_defaults_keep_the_old_flat_charge(): void
    {
        $this->assertSame(70.0, Delivery::fee('ঢাকা', 500));
        $this->assertSame(70.0, Delivery::fee('সিলেট', 500));
        $this->assertTrue(Delivery::isUniform());
        $this->assertNull(Delivery::freeMin());
    }

    public function test_dhaka_and_outside_have_their_own_charge(): void
    {
        $this->setting('delivery_fee_dhaka', '60');
        $this->setting('delivery_fee_outside', '120');

        $this->assertSame(60.0, Delivery::fee('ঢাকা', 500));
        $this->assertSame(60.0, Delivery::fee(' Dhaka ', 500));
        $this->assertSame(120.0, Delivery::fee('চট্টগ্রাম', 500));
        $this->assertSame(120.0, Delivery::fee('N/A', 500), 'unknown district pays the outside rate');
        $this->assertFalse(Delivery::isUniform());
    }

    public function test_free_delivery_at_or_above_the_minimum(): void
    {
        $this->setting('delivery_fee_dhaka', '60');
        $this->setting('delivery_fee_outside', '120');
        $this->setting('delivery_free_min', '1500');

        $this->assertSame(60.0, Delivery::fee('ঢাকা', 1499.99));
        $this->assertSame(0.0, Delivery::fee('ঢাকা', 1500));
        $this->assertSame(0.0, Delivery::fee('সিলেট', 5000));
        $this->assertSame(500.01, round(Delivery::remainingForFree(999.99), 2));
        $this->assertNull(Delivery::remainingForFree(1500));
    }

    public function test_empty_free_minimum_means_off_and_zero_charge_is_allowed(): void
    {
        $this->setting('delivery_free_min', '');
        $this->setting('delivery_fee_dhaka', '0');

        $this->assertNull(Delivery::freeMin());
        $this->assertSame(0.0, Delivery::fee('ঢাকা', 10));
    }

    public function test_quote_is_unknown_until_a_district_is_chosen_when_charges_differ(): void
    {
        $this->setting('delivery_fee_dhaka', '60');
        $this->setting('delivery_fee_outside', '120');

        $this->assertNull(Delivery::quote(null, 500));
        $this->assertSame(60.0, Delivery::quote('ঢাকা', 500));
        $this->assertSame(0.0, Delivery::quote(null, 99999) ?? 0.0, 'free delivery is known without a district') ;
    }

    public function test_charge_shown_on_the_cart_and_policy_pages_matches_settings(): void
    {
        $this->setting('delivery_fee_dhaka', '60');
        $this->setting('delivery_fee_outside', '120');

        $this->get('/delivery')->assertSee('ঢাকার ভেতরে')->assertSee('৳60')->assertSee('৳120');
    }

    public function test_order_service_applies_the_charge_by_district(): void
    {
        $this->setting('delivery_fee_dhaka', '60');
        $this->setting('delivery_fee_outside', '120');

        $dhaka = $this->placeOrder(customer: ['district' => 'ঢাকা', 'phone' => '01711111111']);
        $outside = $this->placeOrder(customer: ['district' => 'খুলনা', 'phone' => '01722222222']);

        $this->assertSame('60.00', $dhaka->shipping_fee);
        $this->assertSame('120.00', $outside->shipping_fee);
        $this->assertSame('620.00', $outside->total);
    }
}
