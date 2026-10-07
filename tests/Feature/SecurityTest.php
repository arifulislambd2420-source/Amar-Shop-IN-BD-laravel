<?php

namespace Tests\Feature;

use App\Support\TrustedProxies;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    public function test_security_headers_are_sent_and_php_is_not_advertised(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeaderMissing('X-Powered-By');
        // Plain HTTP: no HSTS.
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_sent_over_https(): void
    {
        $this->get('https://localhost/')
            ->assertHeader('Strict-Transport-Security');
    }

    public function test_customer_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/customer/login', ['phone' => '01711111111', 'password' => 'wrong'])
                ->assertSessionHasErrors('phone');
        }

        $this->post('/customer/login', ['phone' => '01711111111', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_contact_form_is_rate_limited(): void
    {
        $payload = ['name' => 'A', 'phone' => '01711111111', 'message' => 'hello there'];

        for ($i = 0; $i < 5; $i++) {
            $this->post('/contact', $payload)->assertRedirect();
        }

        $this->post('/contact', $payload)->assertStatus(429);
    }

    public function test_trusted_proxies_default_to_private_ranges_and_wildcard_is_opt_in(): void
    {
        $this->assertSame(TrustedProxies::PRIVATE_RANGES, TrustedProxies::resolve(''));
        $this->assertSame(['203.0.113.7', '10.1.0.0/16'], TrustedProxies::resolve('203.0.113.7, 10.1.0.0/16'));
        $this->assertSame('*', TrustedProxies::resolve('*'));
    }
}
