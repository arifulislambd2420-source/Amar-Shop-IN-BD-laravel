<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Widgets\LatestOrders;
use App\Filament\Widgets\LowStockProducts;
use App\Filament\Widgets\SalesChart;
use App\Filament\Widgets\StatsOverview;
use App\Models\AdminUser;
use App\Models\Banner;
use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\IncompleteOrder;
use App\Models\IpBlock;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/**
 * Every admin screen opens for a super admin: each resource's list, create
 * and edit page and every custom page — with data in it, so tables, forms
 * and relation managers actually render. Filament needs PHP's intl
 * extension (required on the server), so this skips without it.
 */
class AdminPagesTest extends TestCase
{
    use CreatesShopData;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }
    }

    private function admin(): AdminUser
    {
        return AdminUser::create(['username' => 'boss', 'password' => 'secret-pass-1', 'role' => 'super_admin']);
    }

    /** One of everything the admin manages. */
    private function fillShop(): Order
    {
        $category = Category::create(['name' => 'জামা', 'slug' => 'jama']);
        $brand = Brand::create(['name' => 'ব্র্যান্ড']);
        $product = $this->product(['category_id' => $category->id, 'brand_id' => $brand->id, 'price' => 500, 'sale_price' => 450]);
        ProductVariant::create(['product_id' => $product->id, 'label' => 'M', 'price' => 450, 'stock' => 3]);
        Banner::create(['image' => '/storage/media/b.png', 'position' => 'hero', 'is_active' => true]);
        Blog::create(['title' => 'ব্লগ', 'slug' => 'blog-1', 'content' => '<p>লেখা</p>', 'is_published' => true]);
        Coupon::create(['code' => 'EID', 'discount_type' => 'percent', 'discount_value' => 10, 'min_spend' => 0, 'is_active' => true]);
        $sale = FlashSale::create(['title' => 'ফ্ল্যাশ', 'start_time' => now(), 'end_time' => now()->addDay(), 'is_active' => true]);
        $sale->items()->create(['product_id' => $product->id, 'flash_price' => 400]);
        LandingPage::create(['title' => 'ল্যান্ডিং', 'headline' => 'অফার', 'slug' => 'landing-1', 'product_id' => $product->id, 'is_active' => true]);
        Review::create(['product_id' => $product->id, 'customer_name' => 'ক', 'rating' => 5, 'comment' => 'ভালো', 'approved' => false]);
        User::create(['name' => 'গ্রাহক', 'phone' => '01712345678', 'password' => 'secret123']);
        ContactMessage::create(['name' => 'ক', 'phone' => '01712345678', 'email' => 'a@example.com', 'subject' => 'প্রশ্ন', 'message' => 'প্রশ্ন']);
        IpBlock::create(['ip' => '203.0.113.7']);
        IncompleteOrder::capture(Str::random(10), 'checkout', null, '01812345678', 'খ', 'ঢাকা', 'ঠিকানা', [], '203.0.113.8');

        return $this->placeOrder($product);
    }

    public function test_every_admin_page_opens(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->fillShop();

        $panel = Filament::getPanel('admin');
        $urls = [];

        foreach ($panel->getResources() as $resource) {
            foreach (array_keys($resource::getPages()) as $page) {
                $needsRecord = in_array($page, ['edit', 'view'], true);
                $record = $needsRecord ? $resource::getModel()::query()->first() : null;

                if ($needsRecord && ! $record) {
                    continue;
                }

                $urls[] = $resource::getUrl($page, $record ? ['record' => $record] : []);
            }
        }

        foreach ($panel->getPages() as $page) {
            $urls[] = $page::getUrl();
        }

        $this->assertGreaterThan(30, count($urls));

        foreach (array_unique($urls) as $url) {
            $response = $this->get($url);
            $this->assertSame(200, $response->getStatusCode(), "{$url} → {$response->getStatusCode()}\n".Str::limit(strip_tags((string) $response->exception?->getMessage()), 500));
        }
    }

    public function test_every_popup_create_and_edit_form_opens(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->fillShop();
        $checked = 0;

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            $index = $resource::getPages()['index']->getPage();

            if (! is_subclass_of($index, ManageRecords::class)) {
                continue;
            }

            $page = Livewire::test($index);

            if ($resource::canCreate()) {
                $page->mountAction('create')->assertHasNoErrors();
                $checked++;
            }

            if ($record = $resource::getModel()::query()->first()) {
                Livewire::test($index)
                    ->mountAction(TestAction::make('edit')->table($record))
                    ->assertHasNoErrors();
                $checked++;
            }
        }

        $this->assertGreaterThan(10, $checked);
    }

    public function test_cancelling_an_order_in_the_admin_form_returns_its_stock(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $product = $this->product(['stock' => 10]);
        $order = $this->placeOrder($product, quantity: 3);
        $this->assertSame(7, $product->fresh()->stock);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->fillForm(['status' => 'cancelled'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_products_can_be_created_and_edited_from_the_admin(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $category = Category::create(['name' => 'জামা', 'slug' => 'jama']);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'নতুন পাঞ্জাবি', 'slug' => 'new-punjabi', 'status' => 'published',
                'price' => 1200, 'stock' => 5, 'is_active' => true, 'category_id' => $category->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'new-punjabi')->firstOrFail();
        $this->assertEquals(1200, $product->price);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['sale_price' => 999])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(999, $product->fresh()->sale_price);
        $this->get('/product/new-punjabi')->assertOk()->assertSee('নতুন পাঞ্জাবি');
    }

    public function test_popup_forms_save(): void
    {
        $this->actingAs($this->admin(), 'admin');

        Livewire::test(ManageCategories::class)
            ->callAction('create', ['name' => 'জুতা', 'slug' => 'juta'])
            ->assertHasNoFormErrors();
        $this->assertDatabaseHas('categories', ['slug' => 'juta']);

        Livewire::test(ManageCoupons::class)
            ->callAction('create', ['code' => 'NEW10', 'discount_type' => 'percent', 'discount_value' => 10, 'min_spend' => 0, 'is_active' => true])
            ->assertHasNoFormErrors();
        $this->assertDatabaseHas('coupons', ['code' => 'NEW10']);
    }

    public function test_dashboard_shows_today_pending_low_stock_and_sales(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $order = $this->fillShop();

        $this->get('/admin')->assertOk();

        // Widgets load lazily after the page, so check them directly.
        Livewire::test(StatsOverview::class)
            ->assertSee('আজকের অর্ডার')
            ->assertSee('আজকের বিক্রি')
            ->assertSee('পেন্ডিং অর্ডার')
            ->assertSee('কম স্টকের প্রোডাক্ট')
            ->assertSee(Money::taka($order->total));
        Livewire::test(SalesChart::class)->assertSee('বিক্রির চার্ট')->set('filter', '7')->assertOk();
        Livewire::test(LatestOrders::class)->assertSee($order->invoice_no);
        Livewire::test(LowStockProducts::class)->assertOk();
    }

    public function test_admin_uses_the_shop_logo_and_favicon(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->setting('site_logo', '/storage/media/logo.png');

        $this->get('/admin')->assertOk()
            ->assertSee('src="/storage/media/logo.png"', false)
            ->assertSee('href="/storage/media/logo.png"', false);
    }

    public function test_only_a_cancelled_order_can_be_deleted(): void
    {
        $live = $this->placeOrder();

        try {
            $live->delete();
            $this->fail('A live order must not be deletable.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('বাতিল', $e->getMessage());
        }
        $this->assertNotNull($live->fresh());

        $this->assertFalse(OrderResource::canDelete($live));

        $live->update(['status' => 'cancelled']);
        $this->assertTrue(OrderResource::canDelete($live->fresh()));

        $live->fresh()->delete();
        $this->assertNull(Order::find($live->id));
    }

    public function test_bulk_delete_removes_cancelled_orders_and_keeps_the_rest(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $keep = $this->placeOrder();
        $gone = $this->placeOrder(customer: ['phone' => '01812345678']);
        $gone->update(['status' => 'cancelled']);

        Livewire::test(ListOrders::class)
            ->selectTableRecords([$keep->id, $gone->id])
            ->callAction(TestAction::make('deleteCancelled')->table()->bulk());

        $this->assertNotNull($keep->fresh());
        $this->assertNull(Order::find($gone->id));
    }
}
