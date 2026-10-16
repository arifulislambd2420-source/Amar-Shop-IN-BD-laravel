<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\AdminUser;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use App\Support\Money;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/**
 * Any order can be deleted (super admins only). Deleting one that is not cancelled gives its
 * stock back and takes one off its coupon's count; the popup says what is being deleted.
 */
class OrderDeleteTest extends TestCase
{
    use CreatesShopData;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }

        $this->setting('admin_locale', 'en');
        app()->setLocale('en');
    }

    private function superAdmin(): AdminUser
    {
        return AdminUser::create(['username' => 'boss', 'password' => 'secret-pass-1', 'role' => 'super_admin']);
    }

    private function orderWithCoupon(Product $product, int $quantity, string $phone = '01712345678'): Order
    {
        return app(OrderService::class)->createOrder(
            [['product_id' => $product->id, 'variant_id' => null, 'quantity' => $quantity]],
            ['customer_name' => 'Test Customer', 'phone' => $phone, 'district' => 'ঢাকা', 'thana' => 'x', 'address' => 'House 1', 'payment_method' => 'cod'],
            'EID', null, true, true,
        );
    }

    private function coupon(): Coupon
    {
        return Coupon::create(['code' => 'EID', 'discount_type' => 'fixed', 'discount_value' => 50, 'min_spend' => 0, 'is_active' => true]);
    }

    public function test_a_live_order_is_deletable_and_gives_back_stock_and_coupon_use(): void
    {
        $product = $this->product(['stock' => 10]);
        $coupon = $this->coupon();
        $order = $this->orderWithCoupon($product, 3);

        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(1, $coupon->fresh()->uses);
        $this->assertSame($coupon->id, $order->coupon_id);

        $this->actingAs($this->superAdmin(), 'admin');
        $this->assertTrue(OrderResource::canDelete($order));

        $order->delete();

        $this->assertNull(Order::find($order->id));
        $this->assertSame(10, $product->fresh()->stock, 'stock came back');
        $this->assertSame(0, $coupon->fresh()->uses, 'coupon use came back');
    }

    public function test_every_status_can_be_deleted(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        foreach (['pending', 'processing', 'shipped', 'out_for_delivery', 'delivered', 'completed', 'on_hold', 'cancelled'] as $i => $status) {
            $order = $this->placeOrder(customer: ['phone' => '017000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
            $order->update(['status' => $status]);

            $order->fresh()->delete();

            $this->assertNull(Order::find($order->id), $status);
        }
    }

    public function test_a_cancelled_order_gives_back_nothing_a_second_time(): void
    {
        $product = $this->product(['stock' => 10]);
        $coupon = $this->coupon();
        $order = $this->orderWithCoupon($product, 2);

        $order->update(['status' => 'cancelled']);
        $this->assertSame(10, $product->fresh()->stock, 'cancelling returned the stock');

        $order->fresh()->delete();

        $this->assertSame(10, $product->fresh()->stock, 'deleting did not return it again');
        $this->assertSame(1, $coupon->fresh()->uses, 'a cancelled order keeps its coupon use counted');
    }

    public function test_an_order_that_held_no_stock_returns_none(): void
    {
        $product = $this->product(['stock' => 10]);
        $order = $this->placeOrder($product, quantity: 2);
        $order->forceFill(['stock_reserved' => false])->saveQuietly();
        Product::whereKey($product->id)->update(['stock' => 10]);

        $order->delete();

        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_the_coupon_count_never_goes_below_zero_and_old_orders_without_a_coupon_are_fine(): void
    {
        $coupon = $this->coupon();
        $order = $this->orderWithCoupon($this->product(), 1);
        $coupon->update(['uses' => 0]);
        $order->delete();
        $this->assertSame(0, $coupon->fresh()->uses);

        $old = $this->placeOrder(customer: ['phone' => '01812345678']);
        $old->update(['discount' => 40]); // an older order: no coupon_id recorded
        $coupon->update(['uses' => 5]);
        $old->delete();
        $this->assertSame(5, $coupon->fresh()->uses);
    }

    public function test_only_a_super_admin_can_delete_orders(): void
    {
        $order = $this->placeOrder();

        foreach (['manager', 'order_staff'] as $role) {
            $this->actingAs(AdminUser::create(['username' => $role, 'password' => 'secret-pass-1', 'role' => $role]), 'admin');
            $this->assertFalse(OrderResource::canDelete($order), $role);
            $this->assertFalse(OrderResource::canDeleteAny(), $role);

            Livewire::test(ListOrders::class)
                ->selectTableRecords([$order->id])
                ->assertActionHidden(TestAction::make('deleteOrders')->table()->bulk());
        }

        $this->actingAs($this->superAdmin(), 'admin');
        $this->assertTrue(OrderResource::canDelete($order));
        $this->assertNotNull($order->fresh());
    }

    public function test_the_delete_popup_shows_the_order_and_warns_about_a_courier_booking(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');
        $plainOrder = $this->placeOrder(customer: ['customer_name' => 'Rahim Uddin']);
        $courier = $this->placeOrder(customer: ['phone' => '01812345678']);
        $courier->update(['consignment_id' => 'CN-998877', 'status' => 'shipped']);

        $plain = (string) OrderResource::deleteDescription($plainOrder->fresh());
        $this->assertStringContainsString($plainOrder->invoice_no, $plain);
        $this->assertStringContainsString('Rahim Uddin', $plain);
        $this->assertStringContainsString(Money::taka($plainOrder->total), $plain);
        $this->assertStringContainsString('Pending', $plain);
        $this->assertStringNotContainsString('CN-998877', $plain);
        $this->assertStringNotContainsString('courier', strtolower($plain));

        $warned = (string) OrderResource::deleteDescription($courier->fresh());
        $this->assertStringContainsString('CN-998877', $warned);
        $this->assertStringContainsString('will not be cancelled', $warned);

        Livewire::test(ListOrders::class)
            ->mountAction(TestAction::make('delete')->table($courier))
            ->assertMountedActionModalSee([$courier->invoice_no, 'CN-998877']);
    }

    public function test_bulk_delete_removes_orders_of_any_status_with_one_warning(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');
        $product = $this->product(['stock' => 20]);
        $a = $this->placeOrder($product, quantity: 2);
        $b = $this->placeOrder($product, customer: ['phone' => '01812345678'], quantity: 3);
        $c = $this->placeOrder($product, customer: ['phone' => '01912345678'], quantity: 1);
        $b->update(['status' => 'cancelled']); // gives its 3 back
        $c->update(['consignment_id' => 'CN-1', 'status' => 'shipped']);
        $this->assertSame(17, $product->fresh()->stock);

        Livewire::test(ListOrders::class)
            ->selectTableRecords([$a->id, $b->id, $c->id])
            ->mountAction(TestAction::make('deleteOrders')->table()->bulk())
            ->assertMountedActionModalSee(['will not be cancelled', 'Cancelled: 1'])
            ->callMountedAction();

        $this->assertSame(0, Order::count());
        $this->assertSame(20, $product->fresh()->stock, 'the two live orders gave back 2 + 1; the cancelled one nothing more');
    }
}
