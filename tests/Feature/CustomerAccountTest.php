<?php

namespace Tests\Feature;

use App\Livewire\Checkout\CheckoutForm;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\User;
use App\Services\CartService;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use CreatesShopData;

    private function customer(string $phone = '01712345678'): User
    {
        return User::create(['name' => 'রহিম', 'phone' => $phone, 'password' => 'secret123']);
    }

    private function address(User $user, array $overrides = []): CustomerAddress
    {
        return $user->addresses()->create(array_merge([
            'label' => 'বাসা', 'name' => 'রহিম', 'phone' => '01712345678',
            'district' => 'ঢাকা', 'thana' => 'মিরপুর', 'address' => 'বাড়ি ১', 'is_default' => false,
        ], $overrides));
    }

    public function test_account_pages_need_login(): void
    {
        $this->get('/customer/account')->assertRedirect('/customer/login');
        $this->get('/customer/orders/1')->assertRedirect('/customer/login');
    }

    public function test_customer_sees_only_orders_placed_with_their_phone(): void
    {
        $mine = $this->placeOrder(customer: ['phone' => '8801712345678']);
        $theirs = $this->placeOrder(customer: ['phone' => '01899999999']);

        $this->actingAs($this->customer('01712345678'), 'web')
            ->get('/customer/account')
            ->assertOk()
            ->assertSee($mine->invoice_no)
            ->assertDontSee($theirs->invoice_no);
    }

    public function test_order_details_are_scoped_to_the_owner(): void
    {
        $mine = $this->placeOrder(customer: ['phone' => '01712345678']);
        $theirs = $this->placeOrder(customer: ['phone' => '01899999999']);
        $this->actingAs($this->customer(), 'web');

        $this->get('/customer/orders/'.$mine->id)->assertOk()->assertSee($mine->invoice_no)->assertSee('ইনভয়েস');
        $this->get('/customer/orders/'.$theirs->id)->assertNotFound();
    }

    public function test_addresses_can_be_saved_and_the_first_becomes_default(): void
    {
        $user = $this->customer();
        $this->actingAs($user, 'web');

        $this->post('/customer/addresses', [
            'label' => 'বাসা', 'name' => 'রহিম', 'phone' => '+880 1712-345678',
            'district' => 'ঢাকা', 'thana' => 'মিরপুর', 'address' => 'বাড়ি ১, রোড ২',
        ])->assertRedirect();

        $address = $user->addresses()->first();
        $this->assertTrue($address->is_default);
        $this->assertSame('8801712345678', $address->phone, 'phone is stored as digits');
    }

    public function test_address_validation_and_limit(): void
    {
        $user = $this->customer();
        $this->actingAs($user, 'web');

        $this->post('/customer/addresses', ['label' => 'x'])->assertSessionHasErrors(['name', 'district', 'address'], null, 'address');
        $this->post('/customer/addresses', [
            'label' => 'x', 'name' => 'a', 'phone' => '01712345678', 'district' => 'নকল জেলা', 'thana' => 't', 'address' => 'a',
        ])->assertSessionHasErrors('district', null, 'address');

        foreach (range(1, 5) as $i) {
            $this->address($user, ['label' => "ঠিকানা $i"]);
        }
        $this->post('/customer/addresses', [
            'label' => 'ষষ্ঠ', 'name' => 'a', 'phone' => '01712345678', 'district' => 'ঢাকা', 'thana' => 't', 'address' => 'a',
        ])->assertSessionHasErrors('address', null, 'address');
        $this->assertSame(5, $user->addresses()->count());
    }

    public function test_cannot_touch_another_customers_address(): void
    {
        $other = $this->address($this->customer('01899999999'));
        $this->actingAs($this->customer('01712345678'), 'web');

        $this->delete('/customer/addresses/'.$other->id)->assertNotFound();
        $this->post('/customer/addresses/'.$other->id.'/default')->assertNotFound();
        $this->assertNotNull($other->fresh());
    }

    public function test_deleting_the_default_promotes_another_address(): void
    {
        $user = $this->customer();
        $a = $this->address($user, ['is_default' => true]);
        $b = $this->address($user, ['label' => 'অফিস']);
        $this->actingAs($user, 'web');

        $this->delete('/customer/addresses/'.$a->id)->assertRedirect();

        $this->assertTrue($b->fresh()->is_default);
    }

    public function test_checkout_prefills_the_default_address_and_can_save_a_new_one(): void
    {
        $user = $this->customer();
        $this->address($user, ['is_default' => true, 'address' => 'ডিফল্ট বাড়ি', 'thana' => 'গুলশান']);
        $this->actingAs($user, 'web');

        $product = $this->product();
        app(CartService::class)->add($product->id, 1);

        Livewire::test(CheckoutForm::class)
            ->assertSet('address', 'ডিফল্ট বাড়ি')
            ->assertSet('thana', 'গুলশান')
            ->set('address', 'নতুন বাড়ি')
            ->set('saveAddress', true)
            ->call('placeOrder');

        $this->assertSame(1, Order::count());
        $this->assertTrue($user->addresses()->where('address', 'নতুন বাড়ি')->exists());
    }

    public function test_header_links_to_the_account_when_logged_in(): void
    {
        $this->actingAs($this->customer(), 'web')
            ->get('/')
            ->assertSee(route('customer.account'), false);
    }
}
