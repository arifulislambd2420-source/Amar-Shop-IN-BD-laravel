<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Support\StorefrontCache;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use CreatesShopData;

    public function test_404_page_is_site_designed_with_home_and_shop_buttons(): void
    {
        $response = $this->get('/no-such-page')->assertNotFound();

        $response->assertSee('দুঃখিত, পেজটি পাওয়া যায়নি');
        $response->assertSee('হোমে যান');
        $response->assertSee('শপে যান');
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $response->assertSee(route('shop'), false);
    }

    public function test_500_page_is_standalone_and_does_not_leak_details(): void
    {
        $html = view('errors.500')->render();

        $this->assertStringContainsString('সার্ভারে একটি সমস্যা হয়েছে', $html);
        $this->assertStringContainsString(url('/shop'), $html);
        $this->assertStringNotContainsString('@vite', $html);
        $this->assertStringNotContainsString('/build/assets', $html, 'must not depend on the Vite build');
    }

    public function test_blog_link_is_hidden_until_a_post_is_published(): void
    {
        $this->get('/')->assertDontSee(route('blog.index'), false);

        Blog::create(['title' => 'Draft', 'slug' => 'draft', 'content' => 'x', 'published_at' => now()->addDay()]);
        Cache::flush();
        $this->get('/')->assertDontSee(route('blog.index'), false);

        Blog::create(['title' => 'Live', 'slug' => 'live', 'content' => 'x', 'published_at' => now()->subHour()]);
        $this->get('/')->assertSee(route('blog.index'), false);
    }

    public function test_product_page_has_gallery_rich_description_related_products_and_rating(): void
    {
        $category = Category::create(['name' => 'Honey', 'slug' => 'honey']);
        $product = $this->product(['category_id' => $category->id, 'image' => 'https://cdn.test/main.jpg', 'description' => '<p>বিস্তারিত <strong>লেখা</strong></p><script>alert(1)</script>']);
        ProductImage::create(['product_id' => $product->id, 'url' => 'https://cdn.test/extra.jpg', 'alt' => '', 'sort_order' => 1]);
        $related = $this->product(['category_id' => $category->id, 'name' => 'Related Honey']);
        Review::create(['product_id' => $related->id, 'customer_name' => 'A', 'rating' => 4, 'comment' => 'ok', 'approved' => true]);

        $html = $this->get('/product/'.$product->slug)->assertOk()->getContent();

        $this->assertStringContainsString('https://cdn.test/main.jpg', $html);
        $this->assertStringContainsString('https://cdn.test/extra.jpg', $html, 'gallery image');
        $this->assertStringContainsString('<strong>লেখা</strong>', $html, 'rich description');
        $this->assertStringNotContainsString('alert(1)', $html, 'scripts in the description are stripped');
        $this->assertStringContainsString('Related Honey', $html);
        $this->assertStringContainsString('★★★★', $html, 'star rating on the related product card');
    }

    public function test_shop_cards_show_stars_only_for_rated_products(): void
    {
        $rated = $this->product(['name' => 'Rated One']);
        $plain = $this->product(['name' => 'Plain One']);
        Review::create(['product_id' => $rated->id, 'customer_name' => 'A', 'rating' => 5, 'comment' => 'ok', 'approved' => true]);
        Review::create(['product_id' => $plain->id, 'customer_name' => 'B', 'rating' => 5, 'comment' => 'pending', 'approved' => false]);

        $html = $this->get('/shop')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '★★★★★'));
    }

    public function test_brand_logo_shows_on_the_brands_and_brand_filter_pages(): void
    {
        $brand = Brand::create(['name' => 'Deshi', 'logo' => 'https://cdn.test/deshi-logo.png']);
        $this->product(['brand_id' => $brand->id]);

        $this->get('/brands')->assertSee('https://cdn.test/deshi-logo.png', false);
        $this->get('/shop?brand='.$brand->id)->assertSee('https://cdn.test/deshi-logo.png', false)->assertSee('Deshi');
    }

    public function test_images_are_lazy_with_dimensions_except_the_first_hero(): void
    {
        Banner::create(['position' => 'hero', 'image' => 'https://cdn.test/hero1.jpg', 'active' => true, 'sort_order' => 1]);
        Banner::create(['position' => 'hero', 'image' => 'https://cdn.test/hero2.jpg', 'active' => true, 'sort_order' => 2]);
        $this->product(['image' => 'https://cdn.test/p.jpg']);

        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('/<img\b[^>]*>/s', $html, $m);
        foreach ($m[0] as $img) {
            $this->assertMatchesRegularExpression('/\bwidth="\d+"/', $img, $img);
            $this->assertMatchesRegularExpression('/\bheight="\d+"/', $img, $img);
        }

        $first = collect($m[0])->first(fn ($i) => str_contains($i, 'hero1.jpg'));
        $second = collect($m[0])->first(fn ($i) => str_contains($i, 'hero2.jpg'));
        $card = collect($m[0])->first(fn ($i) => str_contains($i, 'p.jpg'));

        $this->assertStringNotContainsString('loading="lazy"', $first, 'the first hero image is not lazy');
        $this->assertStringContainsString('loading="lazy"', $second);
        $this->assertStringContainsString('loading="lazy"', $card);
    }

    public function test_info_pages_use_admin_rich_text_when_set_and_the_builtin_text_when_empty(): void
    {
        $this->get('/about')->assertOk()->assertSee('আমাদের প্রধান লক্ষ্য', false);

        $this->setting('page_about', '<p>আমাদের নতুন <em>গল্প</em></p><script>x()</script>');
        $html = $this->get('/about')->assertOk()->getContent();

        $this->assertStringContainsString('আমাদের নতুন <em>গল্প</em>', $html);
        $this->assertStringNotContainsString('<script>x()', $html);
        $this->assertStringNotContainsString('আমাদের প্রধান লক্ষ্য', $html);

        $this->setting('page_about', '');
        $this->get('/about')->assertSee('আমাদের প্রধান লক্ষ্য', false);
    }

    public function test_categories_and_banners_are_cached_and_cleared_when_edited(): void
    {
        $category = Category::create(['name' => 'Honey', 'slug' => 'honey']);

        $this->assertSame(['Honey'], StorefrontCache::categories()->pluck('name')->all());
        $this->assertTrue(Cache::has('storefront.categories'));

        // Edit → cache cleared → the next read sees the change.
        $category->update(['name' => 'Pure Honey']);
        $this->assertFalse(Cache::has('storefront.categories'));
        $this->assertSame(['Pure Honey'], StorefrontCache::categories()->pluck('name')->all());

        $banner = Banner::create(['position' => 'hero', 'image' => 'a.jpg', 'active' => true, 'sort_order' => 1]);
        $this->assertCount(1, StorefrontCache::banners('hero'));
        $banner->update(['active' => false]);
        $this->assertCount(0, StorefrontCache::banners('hero'));
    }

    public function test_an_order_reducing_stock_does_not_clear_the_catalogue_caches(): void
    {
        $product = $this->product(['stock' => 10]);
        StorefrontCache::categories();
        Cache::put('sitemap.xml', '<x/>', 60);

        $product->decrement('stock');
        $product->update(['stock' => 5]);

        $this->assertTrue(Cache::has('sitemap.xml'), 'stock changes keep the sitemap cache');

        $product->update(['name' => 'Renamed']);
        $this->assertFalse(Cache::has('sitemap.xml'), 'real edits clear it');
    }
public function test_cached_categories_and_banners_survive_the_database_cache_store(): void
    {
        // The production cache is the database store, which refuses to unserialize
        // objects (cache.serializable_classes = false). Models must round-trip.
        config(['cache.default' => 'database']);
        Cache::flush();

        Category::create(['name' => 'Honey', 'slug' => 'honey']);
        Banner::create(['position' => 'hero', 'image' => 'a.jpg', 'active' => true, 'sort_order' => 1]);

        StorefrontCache::categories();
        StorefrontCache::banners('hero');

        $categories = StorefrontCache::categories(); // second call reads from the database cache
        $banners = StorefrontCache::banners('hero');

        $this->assertInstanceOf(Category::class, $categories->first());
        $this->assertSame('Honey', $categories->first()->name);
        $this->assertInstanceOf(Banner::class, $banners->first());
        $this->get('/')->assertOk();
    }
}