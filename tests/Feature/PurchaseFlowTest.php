<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Livewire\Cart\CartPage;
use App\Livewire\Checkout\CheckoutForm;
use App\Livewire\Product\AddToCart;
use App\Livewire\Track\TrackForm;
use App\Models\AdminUser;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\OrderService;
use App\Support\Delivery;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/**
 * The whole way through: product -> size -> cart -> checkout -> success ->
 * the order in the admin -> found on the tracking page; plus stock and
 * coupon bookkeeping.
 */
class PurchaseFlowTest extends TestCase
{
    use CreatesShopData;

    /** A shirt with sizes S/M/L/XL. Product stock = sum of sizes (kept in step by ProductVariant). */
    private function shirt(array $stock = [5, 4, 3, 2], float $price = 800): Product
    {
        $product = $this->product(['name' => 'টেস্ট শার্ট', 'slug' => 'test-shirt', 'price' => $price, 'stock' => 0]);
        foreach (['S', 'M', 'L', 'XL'] as $i => $size) {
            ProductVariant::create(['product_id' => $product->id, 'label' => $size, 'price' => $price, 'stock' => $stock[$i]]);
        }

        return $product->fresh('variants');
    }

    private function fillCheckout($component, array $overrides = [])
    {
        $fields = $overrides + [
            'customer_name' => 'রহিম উদ্দিন',
            'phone' => '01712345678',
            'area' => 'dhaka',
            'thana' => 'মিরপুর',
            'address' => 'বাসা ১০, রোড ৫',
        ];

        foreach ($fields as $key => $value) {
            $component->set($key, $value);
        }

        return $component;
    }

    public function test_a_whole_order_from_product_page_to_tracking(): void
    {
        $shirt = $this->shirt();
        $this->assertSame(14, $shirt->stock, 'product stock follows its sizes');
        $m = $shirt->variants->firstWhere('label', 'M');

        // 1. Product page: pick size M, two of them, add to cart.
        $this->get('/product/test-shirt')->assertOk()->assertSee('সাইজ বাছাই করুন');
        Livewire::test(AddToCart::class, ['productId' => $shirt->id, 'mode' => 'detail'])
            ->call('selectVariant', $m->id)
            ->call('increment')
            ->call('add')
            ->assertSee('কার্টে যোগ করা হয়েছে');

        // 2. Cart.
        Livewire::test(CartPage::class)->assertSee('টেস্ট শার্ট')->assertSee('সাইজ:')->assertSee('M');
        $this->assertSame(2, app(CartService::class)->count());

        // 3. Checkout: Dhaka delivery, place the order.
        $checkout = $this->fillCheckout(Livewire::test(CheckoutForm::class));
        $checkout->assertSet('district', Delivery::DHAKA)->call('placeOrder');

        $order = Order::with('items')->latest('id')->first();
        $this->assertNotNull($order);
        $checkout->assertRedirect(route('order.show', $order->order_token));

        // 4. The order is right.
        $this->assertSame('pending', $order->status);
        $this->assertSame('01712345678', $order->phone);
        $this->assertSame(Delivery::DHAKA, $order->district);
        $this->assertEquals(1600, $order->subtotal);
        $this->assertEquals(Delivery::dhakaFee(), $order->shipping_fee);
        $this->assertEquals(1600 + Delivery::dhakaFee(), $order->total);
        $this->assertSame($m->id, $order->items->first()->variant_id);
        $this->assertSame(2, $order->items->first()->quantity);
        $this->assertTrue($order->stock_reserved);

        // 5. Stock: size M went from 4 to 2, the product total from 14 to 12.
        $this->assertSame(2, $m->fresh()->stock);
        $this->assertSame(12, $shirt->fresh()->stock);

        // 6. Success page, cart emptied.
        $this->get(route('order.show', $order->order_token))->assertOk()
            ->assertSee('আপনার অর্ডার সফল হয়েছে')
            ->assertSee($order->invoice_no)
            ->assertSee('অর্ডার ট্র্যাক করুন');
        $this->assertSame(0, app(CartService::class)->count());

        // 7. Admin sees it in the order list and can open it. (Filament's tables
        //    need PHP's intl extension — required on the server; without it
        //    locally, check the admin's order query instead.)
        $this->actingAs(AdminUser::create(['username' => 'boss', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');
        if (extension_loaded('intl')) {
            $this->get('/admin/orders')->assertOk()->assertSee($order->invoice_no);
            $this->get('/admin/orders/'.$order->id.'/edit')->assertOk()->assertSee('রহিম উদ্দিন');
        } else {
            $this->assertTrue(OrderResource::canViewAny());
            $this->assertTrue(OrderResource::getEloquentQuery()->whereKey($order->id)->exists());
        }

        // 8. Tracking by phone + order number finds it.
        Livewire::test(TrackForm::class)
            ->set('phone', '01712345678')
            ->set('orderId', $order->invoice_no)
            ->call('search')
            ->assertSet('error', '')
            ->assertSee($order->invoice_no)
            ->assertSee('টেস্ট শার্ট (M)');
    }

    public function test_cancelling_returns_stock_once_and_reopening_takes_it_again(): void
    {
        $shirt = $this->shirt();
        $l = $shirt->variants->firstWhere('label', 'L');

        $order = app(OrderService::class)->createOrder(
            [['product_id' => $shirt->id, 'variant_id' => $l->id, 'quantity' => 2]],
            ['customer_name' => 'ক', 'phone' => '01712345678', 'district' => 'ঢাকা', 'thana' => 'x', 'address' => 'y', 'payment_method' => 'cod'],
            bypassCooldown: true,
        );
        $this->assertSame(1, $l->fresh()->stock);

        $order->update(['status' => 'cancelled']);
        $this->assertSame(3, $l->fresh()->stock);
        $this->assertSame(14, $shirt->fresh()->stock);
        $this->assertFalse($order->fresh()->stock_reserved);

        $order->update(['notes' => 'still cancelled']);
        $this->assertSame(3, $l->fresh()->stock, 'no double return');

        $order->update(['status' => 'processing']);
        $this->assertSame(1, $l->fresh()->stock);
        $this->assertSame(12, $shirt->fresh()->stock);

        $order->update(['status' => 'shipped']);
        $this->assertSame(1, $l->fresh()->stock, 'moving along the normal path changes nothing');
    }

    public function test_a_bkash_order_takes_the_ordered_sizes_stock_when_paid(): void
    {
        $shirt = $this->shirt();
        $s = $shirt->variants->firstWhere('label', 'S');

        $order = app(OrderService::class)->createOrder(
            [['product_id' => $shirt->id, 'variant_id' => $s->id, 'quantity' => 3]],
            ['customer_name' => 'ক', 'phone' => '01712345678', 'district' => 'ঢাকা', 'thana' => 'x', 'address' => 'y', 'payment_method' => 'bkash'],
            reserveStock: false,
            bypassCooldown: true,
        );
        $this->assertSame(5, $s->fresh()->stock, 'unpaid bKash holds nothing');

        app(OrderService::class)->finalizeBkashPayment($order, 'TRX1');

        $this->assertSame(2, $s->fresh()->stock);
        $this->assertSame(11, $shirt->fresh()->stock);
        $this->assertTrue($order->fresh()->stock_reserved);
    }

    public function test_an_admin_editing_a_size_keeps_the_product_total_right(): void
    {
        $shirt = $this->shirt([1, 1, 1, 1]);
        $shirt->variants->first()->update(['stock' => 10]);

        $this->assertSame(13, $shirt->fresh()->stock);
    }

    public function test_coupon_works_in_any_case_and_lowers_the_order_total(): void
    {
        $shirt = $this->shirt();
        Coupon::create(['code' => 'EID10', 'discount_type' => 'percent', 'discount_value' => 10, 'min_spend' => 0, 'is_active' => true]);
        app(CartService::class)->add($shirt->id, 2, $shirt->variants->first()->id);

        $checkout = $this->fillCheckout(Livewire::test(CheckoutForm::class))
            ->set('couponCode', 'eid10')
            ->call('applyCoupon')
            ->assertSet('couponError', '')
            ->assertSet('appliedCoupon.discount', 160.0)
            ->call('placeOrder');

        $order = Order::latest('id')->first();
        $this->assertEquals(160, $order->discount);
        $this->assertEquals(1600 + Delivery::dhakaFee() - 160, $order->total);
        $this->assertSame(1, Coupon::first()->uses);
    }

    public function test_coupon_explains_why_it_does_not_apply(): void
    {
        $shirt = $this->shirt();
        app(CartService::class)->add($shirt->id, 1, $shirt->variants->first()->id); // 800

        Coupon::create(['code' => 'BIG', 'discount_type' => 'fixed', 'discount_value' => 100, 'min_spend' => 1000, 'is_active' => true]);
        Coupon::create(['code' => 'OLD', 'discount_type' => 'fixed', 'discount_value' => 100, 'min_spend' => 0, 'valid_until' => now()->subDay(), 'is_active' => true]);
        Coupon::create(['code' => 'DONE', 'discount_type' => 'fixed', 'discount_value' => 100, 'min_spend' => 0, 'max_uses' => 1, 'uses' => 1, 'is_active' => true]);

        $c = Livewire::test(CheckoutForm::class);
        $c->set('couponCode', 'NOPE')->call('applyCoupon')->assertSet('couponError', 'কুপন কোডটি সঠিক নয়।');
        $c->set('couponCode', 'BIG')->call('applyCoupon')->assertSee('কমপক্ষে')->assertSet('appliedCoupon', null);
        $c->set('couponCode', 'OLD')->call('applyCoupon')->assertSet('couponError', 'এই কুপনের মেয়াদ শেষ হয়ে গেছে।');
        $c->set('couponCode', 'DONE')->call('applyCoupon')->assertSet('couponError', 'এই কুপনটি আর ব্যবহার করা যাবে না।');
    }

    public function test_a_percent_coupon_never_takes_more_than_the_subtotal(): void
    {
        $coupon = new Coupon(['discount_type' => 'percent', 'discount_value' => 150]);
        $this->assertEquals(500, $coupon->discountFor(500));

        $fixed = new Coupon(['discount_type' => 'fixed', 'discount_value' => 900]);
        $this->assertEquals(500, $fixed->discountFor(500));
    }

    public function test_checkout_errors_are_in_bangla_and_a_bad_phone_is_refused(): void
    {
        $this->product(['slug' => 'p1']);
        app(CartService::class)->add(Product::first()->id, 1);

        Livewire::test(CheckoutForm::class)
            ->set('phone', '12345')
            ->call('placeOrder')
            ->assertHasErrors(['customer_name', 'phone', 'district', 'thana', 'address'])
            ->assertDispatched('checkout-invalid')
            ->assertSee('আপনার নাম লিখুন।')
            ->assertSee('সঠিক মোবাইল নম্বর দিন');

        $this->assertSame(0, Order::count());
    }

    public function test_delivery_area_sets_the_district_and_the_charge(): void
    {
        $this->setting('delivery_fee_dhaka', '60');
        $this->setting('delivery_fee_outside', '120');
        app(CartService::class)->add($this->product(['price' => 500])->id, 1);

        Livewire::test(CheckoutForm::class)
            ->assertSee('ঢাকার ভেতরে')->assertSee('ঢাকার বাইরে')
            ->set('area', 'dhaka')->assertSet('district', Delivery::DHAKA)
            ->set('area', 'outside')->assertSet('district', '')
            ->set('district', 'চট্টগ্রাম')->assertSet('area', 'outside')
            ->assertSee('৳120');
    }

    public function test_cart_quantity_never_goes_past_stock(): void
    {
        $shirt = $this->shirt([5, 4, 3, 2]);
        $xl = $shirt->variants->firstWhere('label', 'XL');
        app(CartService::class)->add($shirt->id, 1, $xl->id);

        Livewire::test(CartPage::class)
            ->call('updateQuantity', $shirt->id, $xl->id, 2)
            ->call('updateQuantity', $shirt->id, $xl->id, 3)
            ->call('updateQuantity', $shirt->id, $xl->id, 9);

        $this->assertSame(2, app(CartService::class)->all()[$shirt->id.':'.$xl->id]['quantity']);
    }

    public function test_an_out_of_stock_cart_line_is_flagged_and_left_out(): void
    {
        $shirt = $this->shirt([5, 4, 3, 2]);
        $xl = $shirt->variants->firstWhere('label', 'XL');
        app(CartService::class)->add($shirt->id, 1, $xl->id);
        $xl->update(['stock' => 0]);

        Livewire::test(CartPage::class)->assertSee('স্টক শেষ')->assertSee('অর্ডারে যাবে না');
    }
}
