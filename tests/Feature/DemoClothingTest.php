<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MediaLibrary;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\DemoClothingSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

class DemoClothingTest extends TestCase
{
    use CreatesShopData;

    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->publicDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pub-'.uniqid();
        config(['media.public_path' => $this->publicDir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicDir);
        parent::tearDown();
    }

    private function seedDemo(): void
    {
        $this->seed(DemoClothingSeeder::class);
    }

    public function test_it_creates_5_categories_with_4_products_each_all_marked_as_demo(): void
    {
        $this->seedDemo();

        $this->assertSame(5, Category::count());
        $this->assertSame(5, Category::where('is_demo', true)->count());
        $this->assertSame(20, Product::count());
        $this->assertSame(20, Product::where('is_demo', true)->count());

        foreach (Category::all() as $category) {
            $this->assertSame(4, $category->products()->count(), $category->name);
        }

        $this->assertEqualsCanonicalizing(
            ['পাঞ্জাবি', 'শার্ট', 'টি-শার্ট', 'প্যান্ট', 'বোরকা/আবায়া'],
            Category::pluck('name')->all(),
        );
    }

    public function test_every_product_has_bengali_name_description_price_sizes_stock_and_a_local_picture(): void
    {
        $this->seedDemo();

        foreach (Product::with('variants')->get() as $product) {
            $this->assertMatchesRegularExpression('/\p{Bengali}/u', $product->name);
            $this->assertNotSame('', trim((string) $product->description));
            $this->assertGreaterThan(0, (float) $product->price);
            $this->assertStringStartsWith('DEMO-', $product->sku);
            $this->assertTrue($product->is_active);
            $this->assertSame('published', $product->status);

            $this->assertSame(['S', 'M', 'L', 'XL'], $product->variants->pluck('label')->all());
            $this->assertSame($product->variants->sum('stock'), $product->stock);

            $this->assertMatchesRegularExpression('#^/storage/media/demo-[a-z0-9-]+\.png$#', $product->image);
            Storage::disk('public')->assertExists('media/'.basename($product->image));
            $this->assertDatabaseHas('media_library', ['file_path' => $product->image]);
        }
    }

    public function test_some_products_are_discounted_and_variants_carry_the_price_the_cart_charges(): void
    {
        $this->seedDemo();

        $this->assertGreaterThan(0, Product::onSale()->count());
        $this->assertLessThan(20, Product::onSale()->count());

        foreach (Product::with('variants')->get() as $product) {
            foreach ($product->variants as $variant) {
                $this->assertEqualsWithDelta($product->displayPrice(), (float) $variant->price, 0.001, $product->name);
            }
        }
    }

    public function test_the_storefront_shows_the_demo_products(): void
    {
        $this->seedDemo();

        // /shop pages 12 at a time, so look at one category.
        $this->get('/shop?category=demo-punjabi')->assertOk()
            ->assertSee('কটন সাদা পাঞ্জাবি')->assertSee('ডিজাইনার ফতুয়া পাঞ্জাবি');
        $this->get('/product/demo-punjabi-cotton-white')->assertOk()->assertSee('কটন সাদা পাঞ্জাবি');
    }

    public function test_running_it_twice_does_not_duplicate_anything(): void
    {
        $this->seedDemo();
        $this->seedDemo();

        $this->assertSame(5, Category::count());
        $this->assertSame(20, Product::count());
        $this->assertSame(80, ProductVariant::count());
    }

    public function test_it_never_touches_real_data_even_when_a_slug_collides(): void
    {
        $realCategory = Category::create(['name' => 'আমার আসল ক্যাটাগরি', 'slug' => 'demo-shirt']);
        $realProduct = $this->product(['name' => 'আসল পণ্য', 'slug' => 'real-1', 'category_id' => $realCategory->id, 'stock' => 7]);

        $this->seedDemo();

        $realCategory->refresh();
        $this->assertSame('আমার আসল ক্যাটাগরি', $realCategory->name);
        $this->assertFalse((bool) $realCategory->is_demo);
        $this->assertSame(1, Product::where('category_id', $realCategory->id)->count(), 'no demo product goes into a real category');
        $this->assertSame(7, $realProduct->refresh()->stock);
        $this->assertSame(16, Product::where('is_demo', true)->count());
    }

    public function test_demo_remove_deletes_only_demo_data(): void
    {
        $realCategory = Category::create(['name' => 'Real', 'slug' => 'real-cat']);
        $real = $this->product(['category_id' => $realCategory->id]);
        $realVariant = ProductVariant::create(['product_id' => $real->id, 'label' => 'M', 'price' => 500, 'stock' => 3]);
        MediaLibrary::create(['file_name' => 'mine.png', 'file_path' => '/storage/media/0a1b2c.png', 'mime_type' => 'image/png', 'file_size' => 1]);
        Storage::disk('public')->put('media/0a1b2c.png', 'x');

        $this->seedDemo();
        $this->assertSame(21, Product::count());

        $this->artisan('demo:remove', ['--force' => true])->assertSuccessful();

        $this->assertSame(1, Product::withTrashed()->count());
        $this->assertSame(1, Category::count());
        $this->assertSame(1, ProductVariant::count());
        $this->assertNotNull($realVariant->fresh());
        $this->assertTrue($real->fresh()->is_active);
        $this->assertSame(['media/0a1b2c.png'], Storage::disk('public')->files('media'), 'only the real upload is left');
        $this->assertSame(1, MediaLibrary::count());
        $this->assertDatabaseHas('media_library', ['file_path' => '/storage/media/0a1b2c.png']);
    }

    public function test_demo_remove_dry_run_changes_nothing(): void
    {
        $this->seedDemo();

        $this->artisan('demo:remove', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(20, Product::count());
        $this->assertSame(5, Category::count());
        $this->assertCount(20, Storage::disk('public')->files('media'));
    }

    public function test_demo_remove_hides_instead_of_deleting_a_demo_product_that_was_ordered(): void
    {
        $this->seedDemo();
        $ordered = Product::where('slug', 'demo-punjabi-cotton-white')->first();
        $order = $this->placeOrder($ordered);

        $this->artisan('demo:remove', ['--force' => true])->assertSuccessful();

        $this->assertSame(1, Product::withTrashed()->count());
        $kept = $ordered->fresh();
        $this->assertFalse($kept->is_active);
        $this->assertSame('hidden', $kept->status);
        $this->assertNotNull(Order::find($order->id));
        $this->assertSame(1, Category::count(), 'its category stays too');
        Storage::disk('public')->assertExists('media/'.basename($kept->image));
        $this->get('/product/demo-punjabi-cotton-white')->assertNotFound();
    }

    public function test_demo_remove_with_nothing_to_remove_is_a_no_op(): void
    {
        $real = $this->product();

        $this->artisan('demo:remove', ['--force' => true])->assertSuccessful();

        $this->assertNotNull($real->fresh());
    }

    public function test_the_default_seeder_does_not_add_demo_data(): void
    {
        $this->seed();

        $this->assertSame(0, Product::where('is_demo', true)->count());
    }
}
