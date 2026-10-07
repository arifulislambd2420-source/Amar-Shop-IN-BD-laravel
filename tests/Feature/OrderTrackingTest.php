<?php

namespace Tests\Feature;

use App\Livewire\Track\TrackForm;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
{
    use CreatesShopData;

    public function test_a_phone_number_is_always_required(): void
    {
        $order = $this->placeOrder();

        Livewire::test(TrackForm::class)
            ->set('orderId', $order->invoice_no)
            ->set('phone', '')
            ->call('search')
            ->assertSet('result', null)
            ->assertSee('ফোন নম্বর');
    }

    public function test_the_right_phone_finds_the_order(): void
    {
        $order = $this->placeOrder(customer: ['phone' => '01712345678']);

        Livewire::test(TrackForm::class)
            ->set('phone', '01712345678')
            ->call('search')
            ->assertSet('error', '')
            ->assertSee($order->invoice_no)
            ->assertSee('অপেক্ষমাণ');
    }

    public function test_phone_with_the_invoice_number_works_and_formats_are_normalized(): void
    {
        $order = $this->placeOrder(customer: ['phone' => '01712345678']);

        Livewire::test(TrackForm::class)
            ->set('orderId', $order->invoice_no)
            ->set('phone', '+880 1712-345678')
            ->call('search')
            ->assertSee($order->invoice_no);
    }

    public function test_someone_elses_phone_never_returns_the_order(): void
    {
        $order = $this->placeOrder(customer: ['phone' => '01712345678']);

        $component = Livewire::test(TrackForm::class)
            ->set('orderId', $order->invoice_no)
            ->set('phone', '01999999999')
            ->call('search');

        $component->assertSet('result', null)->assertDontSee($order->invoice_no.'</')->assertSee('মিলছে না');
        $this->assertStringNotContainsString('Test Customer', $component->html());
    }

    public function test_phone_alone_needs_a_full_number(): void
    {
        $this->placeOrder(customer: ['phone' => '01712345678']);

        Livewire::test(TrackForm::class)->set('phone', '5678')->call('search')->assertSet('result', null);
    }

    public function test_the_tracking_page_is_fully_bangla(): void
    {
        $html = $this->get('/track')->assertOk()->getContent();

        foreach (['অর্ডার ট্র্যাক করুন', 'ফোন নম্বর', 'অর্ডার খুঁজুন'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['Track Your Order', 'Phone Number', 'Track Order', 'Order ID'] as $english) {
            $this->assertStringNotContainsString($english, $html);
        }
    }

    public function test_lookups_are_rate_limited(): void
    {
        for ($i = 0; $i < 15; $i++) {
            Livewire::test(TrackForm::class)->set('phone', '0171234567'.($i % 10))->call('search');
        }

        $c = Livewire::test(TrackForm::class)->set('phone', '01712345678')->call('search');
        $this->assertStringContainsString('সেকেন্ড', $c->get('error'));

        RateLimiter::clear('track:127.0.0.1');
    }
}
