<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CartService;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use CreatesShopData;

    public function test_login_and_register_pages_render_the_new_form(): void
    {
        $this->get('/customer/login')->assertOk()
            ->assertSee('পাসওয়ার্ড দেখুন')          // show-password button
            ->assertSee('name="remember"', false)
            ->assertSee('autocomplete="current-password"', false);

        $this->get('/customer/register')->assertOk()
            ->assertSee('autocomplete="new-password"', false)
            ->assertSee('কমপক্ষে ৬ অক্ষরের পাসওয়ার্ড দিন।');
    }

    public function test_empty_login_shows_bangla_messages(): void
    {
        $this->from('/customer/login')->post('/customer/login', [])
            ->assertSessionHasErrors([
                'phone' => 'মোবাইল নম্বর লিখুন।',
                'password' => 'পাসওয়ার্ড লিখুন।',
            ]);
    }

    public function test_register_reports_a_bad_phone_and_a_short_password_together(): void
    {
        $this->from('/customer/register')->post('/customer/register', ['name' => 'ক', 'phone' => '12345', 'password' => 'abc'])
            ->assertSessionHasErrors([
                'phone' => 'সঠিক মোবাইল নম্বর দিন (যেমন 01712345678)।',
                'password' => 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।',
            ]);

        $this->assertSame(0, User::count());
    }

    public function test_register_saves_the_number_as_01_whatever_form_is_typed(): void
    {
        $this->post('/customer/register', ['name' => 'রহিম', 'phone' => '+880 1712-345678', 'password' => 'secret123'])
            ->assertRedirect(route('home'));

        $this->assertSame('01712345678', User::first()->phone);
        $this->assertAuthenticated();
    }

    public function test_the_same_number_in_another_form_cannot_register_twice(): void
    {
        User::create(['name' => 'পুরনো', 'phone' => '8801712345678', 'password' => Hash::make('secret123')]);

        $this->from('/customer/register')->post('/customer/register', ['name' => 'নতুন', 'phone' => '01712345678', 'password' => 'secret123'])
            ->assertSessionHasErrors(['phone' => 'এই ফোন নম্বর দিয়ে ইতিমধ্যে একাউন্ট আছে।']);

        $this->assertSame(1, User::count());
    }

    public function test_an_older_account_saved_as_880_can_log_in_with_01(): void
    {
        User::create(['name' => 'পুরনো', 'phone' => '8801712345678', 'password' => Hash::make('secret123')]);

        $this->post('/customer/login', ['phone' => '01712345678', 'password' => 'secret123'])
            ->assertRedirect(route('home'));

        $this->assertAuthenticated();
    }

    public function test_wrong_password_is_refused_with_a_bangla_message(): void
    {
        User::create(['name' => 'ক', 'phone' => '01712345678', 'password' => Hash::make('secret123')]);

        $this->from('/customer/login')->post('/customer/login', ['phone' => '01712345678', 'password' => 'nope'])
            ->assertSessionHasErrors(['phone' => 'ফোন নম্বর অথবা পাসওয়ার্ড সঠিক নয়।']);

        $this->assertGuest();
    }

    public function test_remember_me_sets_the_remember_cookie(): void
    {
        User::create(['name' => 'ক', 'phone' => '01712345678', 'password' => Hash::make('secret123')]);

        $response = $this->post('/customer/login', ['phone' => '01712345678', 'password' => 'secret123', 'remember' => '1']);

        $this->assertNotEmpty(array_filter(
            $response->headers->getCookies(),
            fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'),
        ));
    }

    public function test_header_shows_the_uploaded_logo_or_the_shop_name(): void
    {
        $this->get('/')->assertSee('আমার<span class="text-brand-500">শপ</span>', false);

        $this->setting('site_logo', '/storage/media/logo.png');

        $this->get('/')->assertOk()
            ->assertSee('<img src="/storage/media/logo.png"', false)
            ->assertSee('<link rel="icon" href="/storage/media/logo.png">', false);
    }

    public function test_a_custom_favicon_wins_over_the_logo(): void
    {
        $this->setting('site_logo', '/storage/media/logo.png');
        $this->setting('site_favicon', '/storage/media/icon.png');

        $this->get('/')->assertSee('<link rel="icon" href="/storage/media/icon.png">', false);
    }

    public function test_header_cart_badge_counts_items(): void
    {
        $product = $this->product();
        app(CartService::class)->add($product->id, 3);

        $this->get('/')->assertOk()->assertSee('aria-label="কার্টে 3টি পণ্য"', false);
    }
}
