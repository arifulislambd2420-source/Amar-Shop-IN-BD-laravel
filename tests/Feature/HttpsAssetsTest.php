<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class HttpsAssetsTest extends TestCase
{
    protected function tearDown(): void
    {
        URL::forceScheme(null);
        parent::tearDown();
    }

    public function test_asset_links_are_https_when_app_url_is_https_even_if_the_request_looks_like_http(): void
    {
        config(['app.url' => 'https://shop.example.test']);
        (new AppServiceProvider($this->app))->boot();

        // A plain-http request, as seen behind a proxy whose headers are not trusted.
        $html = $this->get('http://shop.example.test/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="https://shop\.example\.test/build/assets/app-[^"]+\.css"#', $html);
        $this->assertStringNotContainsString('href="http://shop.example.test/build', $html);
    }

    public function test_http_app_url_is_left_alone(): void
    {
        config(['app.url' => 'http://localhost:8000']);
        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('http://', asset('x.css'));
    }
}
