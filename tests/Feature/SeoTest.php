<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Category;
use App\Models\LandingPage;
use App\Models\Review;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use CreatesShopData;

    public function test_home_has_one_h1_canonical_and_organization_schema(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/').'">', $html);
        $this->assertStringContainsString('"@type":"Organization"', $html);
    }

    public function test_default_og_image_comes_from_site_setting_and_pages_can_override_it(): void
    {
        $this->setting('og_image', 'https://cdn.test/default-og.jpg');
        $product = $this->product(['image' => 'https://cdn.test/product.jpg']);

        $this->get('/')->assertSee('<meta property="og:image" content="https://cdn.test/default-og.jpg">', false);
        $this->get('/shop')->assertSee('https://cdn.test/default-og.jpg', false);
        $this->get('/product/'.$product->slug)->assertSee('<meta property="og:image" content="https://cdn.test/product.jpg">', false);
    }

    public function test_product_json_ld_has_price_stock_and_rating(): void
    {
        $product = $this->product(['price' => 900, 'sale_price' => 800, 'stock' => 4]);
        Review::create(['product_id' => $product->id, 'customer_name' => 'A', 'rating' => 5, 'comment' => 'x', 'approved' => true]);
        Review::create(['product_id' => $product->id, 'customer_name' => 'B', 'rating' => 4, 'comment' => 'y', 'approved' => true]);
        Review::create(['product_id' => $product->id, 'customer_name' => 'C', 'rating' => 1, 'comment' => 'hidden', 'approved' => false]);

        $html = $this->get('/product/'.$product->slug)->assertOk()->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $schemas = collect($m[1])->map(fn ($j) => json_decode($j, true))->keyBy('@type');

        $schema = $schemas['Product'];
        $this->assertSame('800.00', $schema['offers']['price']);
        $this->assertSame('BDT', $schema['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $schema['offers']['availability']);
        $this->assertSame(4.5, (float) $schema['aggregateRating']['ratingValue']);
        $this->assertSame(2, $schema['aggregateRating']['reviewCount']);
    }

    public function test_out_of_stock_product_is_marked_out_of_stock(): void
    {
        $product = $this->product(['stock' => 0]);

        $this->get('/product/'.$product->slug)->assertSee('https://schema.org/OutOfStock', false);
    }

    public function test_sitemap_lists_public_pages_but_not_private_or_inactive_ones(): void
    {
        $live = $this->product();
        $hidden = $this->product(['is_active' => false]);
        Category::create(['name' => 'Honey', 'slug' => 'honey']);
        Blog::create(['title' => 'Post', 'slug' => 'a-post', 'content' => 'x', 'published_at' => now()->subDay()]);
        Blog::create(['title' => 'Future', 'slug' => 'future-post', 'content' => 'x', 'published_at' => now()->addDay()]);
        LandingPage::create(['title' => 'On', 'slug' => 'lp-on', 'template' => 'green', 'headline' => 'x', 'is_active' => true]);
        LandingPage::create(['title' => 'Off', 'slug' => 'lp-off', 'template' => 'green', 'headline' => 'x', 'is_active' => false]);

        $response = $this->get('/sitemap.xml')->assertOk();
        $xml = $response->getContent();

        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        foreach ([url('/'), route('shop'), route('product.show', $live->slug), route('shop', ['category' => 'honey']), route('blog.show', 'a-post'), route('landing.show', 'lp-on'), route('about')] as $loc) {
            $this->assertStringContainsString('<loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>', $xml, $loc);
        }
        foreach ([route('product.show', $hidden->slug), route('blog.show', 'future-post'), route('landing.show', 'lp-off'), url('/cart'), url('/checkout'), url('/admin')] as $loc) {
            $this->assertStringNotContainsString(htmlspecialchars($loc, ENT_XML1), $xml, $loc);
        }
        $this->assertNotFalse(simplexml_load_string($xml), 'sitemap is not valid XML');
    }

    public function test_robots_blocks_private_paths_and_points_to_the_sitemap(): void
    {
        $body = $this->get('/robots.txt')->assertOk()->getContent();

        foreach (['/admin', '/cart', '/checkout', '/track'] as $path) {
            $this->assertStringContainsString('Disallow: '.$path, $body);
        }
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $body);
    }

    public function test_private_pages_are_noindex(): void
    {
        foreach (['/cart', '/track', '/customer/login'] as $url) {
            $this->get($url)->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        }
    }

    public function test_landing_page_uses_its_own_seo_fields(): void
    {
        LandingPage::create([
            'title' => 'T', 'slug' => 'seo-lp', 'template' => 'green', 'headline' => 'Headline',
            'seo_title' => 'Custom SEO Title', 'seo_description' => 'Custom description',
            'og_image' => 'https://cdn.test/lp-og.jpg', 'is_active' => true,
        ]);

        $html = $this->get('/lp/seo-lp')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Custom SEO Title</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Custom description">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="https://cdn.test/lp-og.jpg">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/lp/seo-lp').'">', $html);
    }
}
