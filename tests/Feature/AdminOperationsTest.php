<?php

namespace Tests\Feature;

use App\Jobs\SendOrderSms;
use App\Jobs\SendOrderToCourier;
use App\Livewire\Checkout\CheckoutForm;
use App\Models\AdminUser;
use App\Models\IncompleteOrder;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use CreatesShopData;

    private function lead(array $overrides = []): IncompleteOrder
    {
        $product = $this->product(['price' => 500, 'stock' => 5]);

        return IncompleteOrder::capture(
            'sess-1', 'checkout', null, '01712345678', 'রহিম', null, null,
            [['product_id' => $product->id, 'variant_id' => null, 'name' => $product->name, 'quantity' => 2, 'unit_price' => 450.0]],
            '203.0.113.5',
        );
    }

    private function convertData(array $o = []): array
    {
        return array_merge(['customer_name' => 'রহিম', 'phone' => '01712345678', 'district' => 'ঢাকা', 'thana' => 'মিরপুর', 'address' => 'বাড়ি ১'], $o);
    }

    // ── Incomplete orders ────────────────────────────────────────────────

    public function test_a_lead_needs_a_plausible_bangladeshi_mobile_number(): void
    {
        $product = $this->product();
        $item = [['product_id' => $product->id, 'variant_id' => null, 'name' => 'x', 'quantity' => 1, 'unit_price' => 100.0]];

        foreach (['', '12345', '0171234', 'abcdefghijk', '01012345678'] as $bad) {
            $this->assertNull(IncompleteOrder::capture('s', 'checkout', null, $bad, 'x', null, null, $item, null), "'{$bad}'");
        }
        $this->assertNotNull(IncompleteOrder::capture('s', 'checkout', null, '+880 1712-345678', 'x', null, null, $item, null));
        $this->assertNull(IncompleteOrder::capture('s2', 'checkout', null, '01812345678', 'x', null, null, [], null), 'no items, no lead');
    }

    public function test_the_same_person_updates_one_lead_instead_of_creating_many(): void
    {
        $this->lead();
        $this->lead();

        $this->assertSame(1, IncompleteOrder::count());
    }

    public function test_checkout_captures_a_lead_on_phone_blur_and_ordering_closes_it(): void
    {
        $product = $this->product(['price' => 500]);
        app(CartService::class)->add($product->id, 2);

        $component = Livewire::test(CheckoutForm::class)->set('customer_name', 'রহিম')->set('phone', '01712345678');

        $lead = IncompleteOrder::first();
        $this->assertSame('01712345678', $lead->phone);
        $this->assertSame(1000.0, (float) $lead->total);
        $this->assertSame($product->name, $lead->items[0]['name']);

        $component->set('district', 'ঢাকা')->set('thana', 'x')->set('address', 'y')->call('placeOrder');

        $this->assertSame('converted', $lead->fresh()->status);
        $this->assertSame(Order::first()->id, $lead->fresh()->order_id);
    }

    public function test_admin_can_convert_a_lead_into_a_cod_order(): void
    {
        $lead = $this->lead();
        $product = Product::find($lead->items[0]['product_id']);

        $order = $lead->convertToOrder($this->convertData());

        $this->assertSame('cod', $order->payment_method);
        $this->assertSame('900.00', $order->subtotal, 'price the customer saw × quantity');
        $this->assertSame(3, $product->fresh()->stock, 'stock reduced');
        $this->assertSame('converted', $lead->fresh()->status);
        $this->assertSame($order->id, $lead->fresh()->order_id);
    }

    public function test_conversion_ignores_the_duplicate_cooldown_but_reports_missing_stock(): void
    {
        $this->placeOrder(customer: ['phone' => '01712345678'], bypassCooldown: true);
        $lead = $this->lead();

        $this->assertNotNull($lead->convertToOrder($this->convertData()), 'the admin phoned them, so no cooldown');

        $other = IncompleteOrder::capture('s9', 'checkout', null, '01912345678', 'x', null, null,
            [['product_id' => $this->product(['stock' => 0])->id, 'variant_id' => null, 'name' => 'Gone', 'quantity' => 1, 'unit_price' => 10.0]], null);

        $this->expectException(RuntimeException::class);
        $other->convertToOrder($this->convertData(['phone' => '01912345678']));
    }

    // ── Landing page report ──────────────────────────────────────────────

    public function test_landing_page_orders_and_views_give_a_conversion_rate(): void
    {
        $product = $this->product();
        $lp = LandingPage::create(['title' => 'LP', 'slug' => 'rep', 'template' => 'green', 'headline' => 'x', 'product_id' => $product->id, 'is_active' => true,
            'blocks' => [['type' => 'order_form', 'data' => ['hidden' => false]]]]);

        for ($i = 0; $i < 4; $i++) {
            $this->get('/lp/rep')->assertOk();
        }
        $this->assertSame(4, $lp->fresh()->views);

        $this->placeOrder($product, ['phone' => '01711111111']);
        Order::latest('id')->first()->update(['landing_page_id' => $lp->id]);
        $cancelled = $this->placeOrder($product, ['phone' => '01722222222']);
        $cancelled->update(['landing_page_id' => $lp->id, 'status' => 'cancelled']);

        $row = LandingPage::withCount(['orders' => fn ($q) => $q->counted()])->find($lp->id);
        $this->assertSame(1, $row->orders_count, 'cancelled orders are not conversions');
        $this->assertSame(25.0, $row->orders_count / $row->views * 100);
    }

    // ── Low-stock threshold ──────────────────────────────────────────────

    public function test_low_stock_threshold_defaults_to_five_and_is_configurable(): void
    {
        $this->assertSame(5, Product::lowStockThreshold());

        $this->setting('low_stock_threshold', '12');
        $this->assertSame(12, Product::lowStockThreshold());

        $this->setting('low_stock_threshold', '0');
        $this->assertSame(0, Product::lowStockThreshold());
    }

    public function test_low_stock_alert_widget_only_shows_when_something_is_low(): void
    {
        $admin = AdminUser::create(['username' => 'a', 'password' => 'secret-pass-1', 'role' => 'super_admin']);
        $this->actingAs($admin, 'admin');

        $this->product(['stock' => 50]);
        $this->assertFalse(\App\Filament\Widgets\LowStockAlert::canView());

        $this->product(['stock' => 3]);
        $this->assertTrue(\App\Filament\Widgets\LowStockAlert::canView());

        $this->setting('low_stock_threshold', '1');
        $this->assertFalse(\App\Filament\Widgets\LowStockAlert::canView(), 'stock 3 is above a threshold of 1');
    }

    // ── Queue ────────────────────────────────────────────────────────────

    public function test_cod_order_queues_the_confirmation_sms_instead_of_calling_the_gateway_inline(): void
    {
        Queue::fake();

        $order = $this->placeOrder();
        $this->app->terminate();

        Queue::assertPushed(SendOrderSms::class, fn (SendOrderSms $job) => $job->orderId === $order->id && $job->event === 'confirmed');
    }

    public function test_courier_job_records_the_failure_on_the_order(): void
    {
        $order = $this->placeOrder();

        (new SendOrderToCourier($order->id, 'steadfast'))->handle();

        $this->assertStringContainsString('Steadfast', $order->fresh()->courier_error);
        $this->assertNull($order->fresh()->consignment_id);
    }

    public function test_courier_job_clears_the_error_on_success(): void
    {
        $order = $this->placeOrder();
        $order->forceFill(['courier_error' => 'old failure'])->save();

        (new SendOrderToCourier($order->id, 'pathao'))->handle(); // mocked courier: always succeeds

        $this->assertNull($order->fresh()->courier_error);
        $this->assertNotNull($order->fresh()->consignment_id);
    }
}
