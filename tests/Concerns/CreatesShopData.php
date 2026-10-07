<?php

namespace Tests\Concerns;

use App\Models\Order;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\OrderService;

/** Small helpers so feature tests can set up a shop in one line. */
trait CreatesShopData
{
    protected function product(array $overrides = []): Product
    {
        static $n = 0;
        $n++;

        return Product::create(array_merge([
            'name' => "Test Product {$n}",
            'slug' => "test-product-{$n}",
            'price' => 500,
            'stock' => 50,
            'is_active' => true,
            'status' => 'published',
        ], $overrides));
    }

    protected function setting(string $key, string $value): void
    {
        SiteSetting::updateOrCreate(['setting_key' => $key], ['setting_value' => $value]);
        \App\Support\SiteSettingsHelper::forget($key);
    }

    /** Places an order through the real OrderService (cooldown bypassed unless asked). */
    protected function placeOrder(?Product $product = null, array $customer = [], int $quantity = 1, bool $bypassCooldown = true): Order
    {
        $product ??= $this->product();

        return app(OrderService::class)->createOrder(
            [['product_id' => $product->id, 'variant_id' => null, 'quantity' => $quantity]],
            array_merge([
                'customer_name' => 'Test Customer',
                'phone' => '01712345678',
                'district' => 'ঢাকা',
                'thana' => 'x',
                'address' => 'House 1',
                'payment_method' => 'cod',
                'ip_address' => '203.0.113.9',
            ], $customer),
            bypassCooldown: $bypassCooldown,
        );
    }
}
