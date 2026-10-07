<?php

namespace Tests\Feature;

use App\Livewire\Checkout\CheckoutForm;
use App\Models\Order;
use App\Services\CartService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use CreatesShopData;

    private function fill($component, array $overrides = [])
    {
        foreach (array_merge([
            'customer_name' => 'রহিম',
            'phone' => '01712345678',
            'district' => 'ঢাকা',
            'thana' => 'মিরপুর',
            'address' => 'বাড়ি ১, রোড ২',
        ], $overrides) as $field => $value) {
            $component->set($field, $value);
        }

        return $component;
    }

    public function test_cod_order_is_created_with_delivery_charge_and_stock_is_reduced(): void
    {
        $this->setting('delivery_fee_dhaka', '60');
        $product = $this->product(['price' => 500, 'stock' => 10]);
        app(CartService::class)->add($product->id, 2);

        $component = $this->fill(Livewire::test(CheckoutForm::class))->call('placeOrder');

        $order = Order::latest('id')->first();
        $component->assertRedirect(route('order.show', $order->order_token));

        $this->assertSame('1000.00', $order->subtotal);
        $this->assertSame('60.00', $order->shipping_fee);
        $this->assertSame('1060.00', $order->total);
        $this->assertSame('cod', $order->payment_method);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertEmpty(app(CartService::class)->all(), 'cart is cleared after ordering');
    }

    public function test_charge_depends_on_district_and_free_delivery_threshold(): void
    {
        $this->setting('delivery_fee_dhaka', '60');
        $this->setting('delivery_fee_outside', '130');
        $this->setting('delivery_free_min', '2000');

        $cheap = $this->product(['price' => 500]);
        $big = $this->product(['price' => 2500]);

        app(CartService::class)->add($cheap->id, 1);
        $this->fill(Livewire::test(CheckoutForm::class), ['district' => 'সিলেট'])->call('placeOrder');
        $this->assertSame('130.00', Order::latest('id')->first()->shipping_fee);

        app(CartService::class)->clear();
        app(CartService::class)->add($big->id, 1);
        $this->fill(Livewire::test(CheckoutForm::class), ['district' => 'সিলেট', 'phone' => '01812345678'])->call('placeOrder');
        $this->assertSame('0.00', Order::latest('id')->first()->shipping_fee, 'free delivery over the minimum');
    }

    public function test_required_fields_are_validated(): void
    {
        $product = $this->product();
        app(CartService::class)->add($product->id, 1);

        Livewire::test(CheckoutForm::class)
            ->call('placeOrder')
            ->assertHasErrors(['customer_name', 'phone', 'district', 'thana', 'address']);

        $this->assertSame(0, Order::count());
    }

    public function test_out_of_stock_cart_cannot_be_ordered(): void
    {
        $product = $this->product(['stock' => 0]);
        app(CartService::class)->add($product->id, 1);

        $component = $this->fill(Livewire::test(CheckoutForm::class))->call('placeOrder');

        $this->assertSame(0, Order::count());
        $this->assertNotSame('', $component->get('error'));
    }

    public function test_checkout_is_rate_limited_per_ip(): void
    {
        $product = $this->product(['stock' => 100]);
        // Each attempt is from a different phone so only the IP limiter can stop it.
        for ($i = 0; $i < 6; $i++) {
            app(CartService::class)->add($product->id, 1);
            $this->fill(Livewire::test(CheckoutForm::class), ['phone' => '0171234567'.$i])->call('placeOrder');
            app(CartService::class)->clear();
        }

        app(CartService::class)->add($product->id, 1);
        $before = Order::count();
        $component = $this->fill(Livewire::test(CheckoutForm::class), ['phone' => '01799999999'])->call('placeOrder');

        $this->assertSame($before, Order::count(), 'the 7th attempt creates nothing');
        $this->assertStringContainsString('সেকেন্ড', $component->get('error'));

        RateLimiter::clear('checkout-order:127.0.0.1');
    }
}
