<?php

namespace Tests\Feature;

use App\Services\OrderService;
use RuntimeException;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

class DuplicateOrderTest extends TestCase
{
    use CreatesShopData;

    public function test_same_phone_cannot_order_again_inside_the_cooldown(): void
    {
        $this->placeOrder(customer: ['phone' => '01712345678'], bypassCooldown: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('সম্প্রতি একটি অর্ডার');

        $this->placeOrder(customer: ['phone' => '+880 1712-345678'], bypassCooldown: false);
    }

    public function test_phone_formats_are_treated_as_the_same_number(): void
    {
        $this->placeOrder(customer: ['phone' => '01712345678'], bypassCooldown: false);

        foreach (['8801712345678', '+8801712345678', '1712345678'] as $format) {
            try {
                $this->placeOrder(customer: ['phone' => $format], bypassCooldown: false);
                $this->fail("{$format} should have been blocked");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('সম্প্রতি', $e->getMessage());
            }
        }
    }

    public function test_a_different_phone_is_not_blocked(): void
    {
        $this->placeOrder(customer: ['phone' => '01712345678'], bypassCooldown: false);
        $order = $this->placeOrder(customer: ['phone' => '01812345678'], bypassCooldown: false);

        $this->assertSame(2, \App\Models\Order::count());
        $this->assertNotNull($order->id);
    }

    public function test_the_same_phone_can_order_after_the_cooldown_passes(): void
    {
        $this->setting('order_cooldown_minutes', '10');
        $this->placeOrder(customer: ['phone' => '01712345678'], bypassCooldown: false);

        $this->travel(11)->minutes();

        $this->placeOrder(customer: ['phone' => '01712345678'], bypassCooldown: false);
        $this->assertSame(2, \App\Models\Order::count());
    }

    public function test_cooldown_length_comes_from_the_setting_and_zero_turns_it_off(): void
    {
        $this->assertSame(10, OrderService::cooldownMinutes());

        $this->setting('order_cooldown_minutes', '30');
        $this->assertSame(30, OrderService::cooldownMinutes());

        $this->setting('order_cooldown_minutes', '0');
        $this->placeOrder(customer: ['phone' => '01712345678'], bypassCooldown: false);
        $this->placeOrder(customer: ['phone' => '01712345678'], bypassCooldown: false);
        $this->assertSame(2, \App\Models\Order::count());
    }

    public function test_cancelled_orders_do_not_block_a_retry(): void
    {
        $first = $this->placeOrder(customer: ['phone' => '01712345678'], bypassCooldown: false);
        $first->update(['status' => 'cancelled']);

        $this->placeOrder(customer: ['phone' => '01712345678'], bypassCooldown: false);
        $this->assertSame(2, \App\Models\Order::count());
    }
}
