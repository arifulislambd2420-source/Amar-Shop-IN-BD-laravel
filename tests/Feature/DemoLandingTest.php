<?php

namespace Tests\Feature;

use App\Livewire\Landing\LandingOrderForm;
use App\Models\LandingPage;
use App\Models\MediaLibrary;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\DemoLandingSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/** The six demo landing pages (one per template) and the models/colours picker behind them. */
class DemoLandingTest extends TestCase
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
        $this->seed(DemoLandingSeeder::class);
    }

    public function test_it_creates_one_demo_page_per_template_each_with_its_own_product_and_3_or_4_colours(): void
    {
        $this->seedDemo();

        $this->assertEqualsCanonicalizing(
            ['green', 'purple', 'cream', 'template-1', 'template-2', 'template-3'],
            LandingPage::pluck('template')->all(),
        );
        $this->assertSame(6, LandingPage::where('is_demo', true)->count());
        $this->assertSame(6, Product::where('is_demo', true)->count());
        $this->assertSame(6, LandingPage::distinct()->count('product_id'), 'a product each');

        foreach (LandingPage::with('product.variants')->get() as $page) {
            $variants = $page->product->variants;
            $this->assertGreaterThanOrEqual(3, $variants->count(), $page->slug);
            $this->assertLessThanOrEqual(4, $variants->count(), $page->slug);
            $this->assertSame($variants->sum('stock'), $page->product->stock);

            foreach ($variants as $v) {
                $this->assertNotEmpty($v->color);
                $this->assertGreaterThan(0, (float) $v->price);
                $this->assertMatchesRegularExpression('#^/storage/media/demo-lp-[a-z0-9-]+\.png$#', $v->image);
                Storage::disk('public')->assertExists('media/'.basename($v->image));
            }
            $this->assertSame($variants->count(), $variants->pluck('image')->unique()->count(), 'a picture per colour');
        }
    }

    public function test_every_demo_page_opens_and_shows_its_colours_and_the_order_form(): void
    {
        $this->seedDemo();

        foreach (LandingPage::with('product.variants')->get() as $page) {
            $response = $this->get('/lp/'.$page->slug)->assertOk()->assertSee('অর্ডার');

            foreach ($page->product->variants as $v) {
                $response->assertSee($v->color);
            }
        }
    }

    public function test_the_new_blocks_render_on_a_builder_page(): void
    {
        $this->seedDemo();

        $this->get('/lp/demo-lp-green')->assertOk()
            ->assertSee('আজকের অফার')                    // price block
            ->assertSee('% ছাড়')                          // discount
            ->assertSee('আপনি বাঁচাচ্ছেন')
            ->assertSee('আপনার পছন্দের রং বেছে নিন')      // models block
            ->assertSee('select-model', false)
            ->assertSee('কেন আমাদের উপর ভরসা করবেন?')     // trust block
            ->assertSee('কেন আমাদের পণ্যটি কিনবেন?');     // why-buy features
    }

    public function test_models_block_shows_nothing_for_a_product_without_variants(): void
    {
        $product = $this->product(['price' => 500]);
        LandingPage::create([
            'title' => 'Plain', 'slug' => 'plain', 'template' => 'green', 'headline' => 'Plain', 'product_id' => $product->id,
            'is_active' => true, 'button_text' => 'অর্ডার',
            'blocks' => [['type' => 'models', 'data' => ['heading' => 'মডেল-শিরোনাম']], ['type' => 'order_form', 'data' => []]],
        ]);

        $this->get('/lp/plain')->assertOk()->assertDontSee('মডেল-শিরোনাম');
    }

    public function test_ordering_a_chosen_colour_charges_its_price_and_takes_its_stock_on_every_template(): void
    {
        $this->seedDemo();

        foreach (LandingPage::with('product.variants')->orderBy('id')->get() as $n => $page) {
            $variant = $page->product->variants->sortBy('id')->values()[1];
            $before = $variant->stock;

            Livewire::test(LandingOrderForm::class, ['landingPage' => $page])
                ->call('selectModel', 1)
                ->assertSet('packageIndex', 1)
                ->set('customer_name', 'রহিম')->set('phone', '0171234560'.$n)->set('address', 'ঢাকা, মিরপুর')
                ->set('quantity', 2)
                ->call('placeOrder');

            $order = Order::latest('id')->first();
            $item = $order->items->first();

            $this->assertSame($page->id, $order->landing_page_id, $page->slug);
            $this->assertSame($variant->id, $item->variant_id, $page->slug);
            $this->assertSame(2, $item->quantity);
            $this->assertEqualsWithDelta((float) $variant->price * 2, (float) $order->subtotal, 0.01, $page->slug);
            $this->assertStringContainsString('মডেল: '.$variant->color, $order->notes);
            $this->assertSame($before - 2, $variant->fresh()->stock, $page->slug);
        }
    }

    public function test_a_sold_out_colour_cannot_be_ordered(): void
    {
        $this->seedDemo();
        $page = LandingPage::with('product.variants')->where('slug', 'demo-lp-green')->first();
        $page->product->variants->sortBy('id')->values()[0]->update(['stock' => 0]);

        Livewire::test(LandingOrderForm::class, ['landingPage' => $page])
            ->set('packageIndex', 0)
            ->set('customer_name', 'রহিম')->set('phone', '01712345678')->set('address', 'ঢাকা')
            ->call('placeOrder')
            ->assertSet('error', fn ($e) => str_contains($e, 'স্টক'));

        $this->assertSame(0, Order::count());
    }

    public function test_the_page_price_override_still_wins_over_colour_prices(): void
    {
        $this->seedDemo();
        $page = LandingPage::with('product.variants')->where('slug', 'demo-lp-purple')->first();
        $page->update(['price_override' => 700]);

        Livewire::test(LandingOrderForm::class, ['landingPage' => $page->fresh()])
            ->set('packageIndex', 2)
            ->set('customer_name', 'রহিম')->set('phone', '01712345678')->set('address', 'ঢাকা')
            ->call('placeOrder');

        $this->assertSame('700.00', Order::latest('id')->first()->subtotal);
    }

    public function test_running_it_twice_does_not_duplicate_and_never_touches_real_pages(): void
    {
        $real = LandingPage::create(['title' => 'আসল', 'slug' => 'demo-lp-green', 'template' => 'green', 'headline' => 'আসল', 'is_active' => true, 'button_text' => 'x']);

        $this->seedDemo();
        $this->seedDemo();

        $this->assertSame(6, LandingPage::count(), 'real page kept + 5 new demo pages');
        $this->assertFalse((bool) $real->fresh()->is_demo);
        $this->assertSame('আসল', $real->fresh()->title);
    }

    public function test_demo_remove_deletes_the_demo_pages_products_colours_and_pictures_only(): void
    {
        $realProduct = $this->product();
        $realVariant = ProductVariant::create(['product_id' => $realProduct->id, 'label' => 'M', 'price' => 500, 'stock' => 3]);
        $realPage = LandingPage::create(['title' => 'আসল', 'slug' => 'real-page', 'template' => 'green', 'headline' => 'আসল', 'product_id' => $realProduct->id, 'is_active' => true, 'button_text' => 'x']);
        MediaLibrary::create(['file_name' => 'mine.png', 'file_path' => '/storage/media/0a1b2c.png', 'mime_type' => 'image/png', 'file_size' => 1]);
        Storage::disk('public')->put('media/0a1b2c.png', 'x');

        $this->seedDemo();
        $this->assertSame(7, LandingPage::count());

        Artisan::call('demo:remove', ['--force' => true]);

        $this->assertSame(1, LandingPage::count());
        $this->assertTrue($realPage->fresh()->is_active);
        $this->assertSame(1, Product::count());
        $this->assertSame(1, ProductVariant::count());
        $this->assertNotNull($realVariant->fresh());
        $this->assertSame(0, MediaLibrary::where('file_path', 'like', '/storage/media/demo-%')->count());
        $this->assertSame([], collect(Storage::disk('public')->files('media'))->filter(fn ($f) => str_starts_with(basename($f), 'demo-'))->all());
        Storage::disk('public')->assertExists('media/0a1b2c.png');
    }

    public function test_demo_remove_switches_off_a_demo_page_that_got_an_order_instead_of_deleting_it(): void
    {
        $this->seedDemo();
        $page = LandingPage::with('product.variants')->where('slug', 'demo-lp-cream')->first();

        Livewire::test(LandingOrderForm::class, ['landingPage' => $page])
            ->set('customer_name', 'রহিম')->set('phone', '01712345678')->set('address', 'ঢাকা')
            ->call('placeOrder');
        $this->assertSame(1, Order::count());

        Artisan::call('demo:remove', ['--force' => true]);

        $this->assertSame(1, LandingPage::count(), 'only the ordered one is left');
        $kept = LandingPage::first();
        $this->assertSame('demo-lp-cream', $kept->slug);
        $this->assertFalse($kept->is_active);
        $this->assertSame(1, Order::count());
        $this->get('/lp/demo-lp-cream')->assertNotFound();
        // Its product (ordered) is hidden, not deleted, so its picture stays too.
        $this->assertSame(1, Product::withTrashed()->count());
        $this->assertSame('hidden', Product::first()->status);
        $this->assertNotNull(ProductVariant::first()?->image);
    }
}
