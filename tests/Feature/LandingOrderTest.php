<?php

namespace Tests\Feature;

use App\Livewire\Landing\LandingOrderForm;
use App\Models\IncompleteOrder;
use App\Models\LandingPage;
use App\Models\Order;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

class LandingOrderTest extends TestCase
{
    use CreatesShopData;

    private function page(array $attrs = []): LandingPage
    {
        $product = $attrs['__product'] ?? $this->product(['price' => 500]);
        unset($attrs['__product']);

        return LandingPage::create(array_merge([
            'title' => 'LP', 'slug' => 'lp-'.uniqid(), 'template' => 'green', 'headline' => 'LP',
            'product_id' => $product->id, 'is_active' => true, 'button_text' => 'অর্ডার',
            'blocks' => [['type' => 'order_form', 'data' => ['hidden' => false]]],
            'packages' => [
                ['product_id' => $product->id, 'label' => '১ পিস', 'price' => 480, 'compare_price' => 600, 'quantity' => 1],
                ['product_id' => $product->id, 'label' => '৩ পিস', 'price' => 1150, 'quantity' => 3],
            ],
        ], $attrs));
    }

    private function order($lp, array $set = [])
    {
        $c = Livewire::test(LandingOrderForm::class, ['landingPage' => $lp])
            ->set('customer_name', 'রহিম')->set('phone', '01712345678')->set('address', 'ঢাকা, মিরপুর');

        foreach ($set as $k => $v) {
            $c->set($k, $v);
        }

        return $c->call('placeOrder');
    }

    public function test_package_price_and_quantity_come_from_the_server(): void
    {
        $this->setting('delivery_fee_dhaka', '70');
        $lp = $this->page();

        $this->order($lp, ['packageIndex' => 1, 'zone' => 'dhaka']);

        $order = Order::latest('id')->first();
        $this->assertSame('1150.00', $order->subtotal);
        $this->assertSame('1220.00', $order->total);
        $this->assertSame(3, $order->items->first()->quantity);
        $this->assertSame($lp->id, $order->landing_page_id);
        $this->assertStringContainsString('প্যাকেজ: ৩ পিস', $order->notes);
    }

    public function test_quantity_multiplies_the_package(): void
    {
        $lp = $this->page();

        $this->order($lp, ['packageIndex' => 0, 'quantity' => 2]);

        $order = Order::latest('id')->first();
        $this->assertSame('960.00', $order->subtotal);
        $this->assertSame(2, $order->items->first()->quantity);
    }

    public function test_tampered_package_index_falls_back_instead_of_inventing_a_price(): void
    {
        $lp = $this->page();

        $this->order($lp, ['packageIndex' => 99]);

        $this->assertSame('480.00', Order::latest('id')->first()->subtotal, 'unknown index = first package');
    }

    public function test_delivery_zone_changes_the_charge_and_is_recorded(): void
    {
        $this->setting('delivery_fee_dhaka', '70');
        $this->setting('delivery_fee_outside', '130');
        $lp = $this->page();

        $this->order($lp, ['zone' => 'outside']);

        $order = Order::latest('id')->first();
        $this->assertSame('130.00', $order->shipping_fee);
        $this->assertSame('ঢাকার বাইরে', $order->district);
    }

    public function test_size_and_colour_are_validated_against_the_page_options(): void
    {
        $lp = $this->page(['blocks' => [
            ['type' => 'variants', 'data' => ['size_enabled' => true, 'sizes' => ['M', 'L'], 'size_required' => true, 'color_enabled' => false, 'hidden' => false]],
            ['type' => 'order_form', 'data' => ['hidden' => false]],
        ]]);

        $this->order($lp, ['size' => 'XXL'])->assertHasErrors('size');
        $this->assertSame(0, Order::count());

        $this->order($lp, ['size' => 'L']);
        $this->assertStringContainsString('সাইজ: L', Order::latest('id')->first()->notes);
    }

    public function test_hidden_variants_block_removes_the_fields(): void
    {
        $lp = $this->page(['blocks' => [
            ['type' => 'variants', 'data' => ['size_enabled' => true, 'sizes' => ['M'], 'size_required' => true, 'hidden' => true]],
            ['type' => 'order_form', 'data' => ['hidden' => false]],
        ]]);

        $this->order($lp)->assertHasNoErrors();
        $this->assertSame(1, Order::count());
    }

    public function test_inactive_page_cannot_take_orders(): void
    {
        $lp = $this->page(['is_active' => false]);

        $this->order($lp);

        $this->assertSame(0, Order::count());
    }

    public function test_legacy_template_still_works_with_the_page_price(): void
    {
        $product = $this->product(['price' => 500]);
        $lp = LandingPage::create([
            'title' => 'Old', 'slug' => 'old', 'template' => 'template-1', 'headline' => 'Old',
            'product_id' => $product->id, 'price_override' => 350, 'is_active' => true,
        ]);

        $this->order($lp, ['quantity' => 2]);

        $order = Order::latest('id')->first();
        $this->assertSame('700.00', $order->subtotal);
        $this->get('/lp/old')->assertOk();
    }

    public function test_entering_a_phone_creates_an_incomplete_order_and_ordering_closes_it(): void
    {
        $lp = $this->page();

        Livewire::test(LandingOrderForm::class, ['landingPage' => $lp])
            ->set('customer_name', 'রহিম')
            ->set('phone', '01712345678');

        $lead = IncompleteOrder::first();
        $this->assertNotNull($lead);
        $this->assertSame('open', $lead->status);
        $this->assertSame('landing', $lead->source);
        $this->assertSame($lp->id, $lead->landing_page_id);
        $this->assertSame(480.0, (float) $lead->total);

        $this->order($lp);

        $this->assertSame('converted', $lead->fresh()->status);
        $this->assertNotNull($lead->fresh()->order_id);
    }

    public function test_invalid_phone_is_not_captured(): void
    {
        Livewire::test(LandingOrderForm::class, ['landingPage' => $this->page()])->set('phone', '123');

        $this->assertSame(0, IncompleteOrder::count());
    }
}
