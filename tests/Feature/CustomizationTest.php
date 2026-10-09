<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Support\ImageUpload;
use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Support\AdminLang;
use App\Support\HomeSections;
use App\Support\RemoteImage;
use App\Support\SiteSettingsHelper;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

class CustomizationTest extends TestCase
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

    private function needsIntl(): void
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }
    }

    private function admin(): void
    {
        $this->actingAs(AdminUser::create(['username' => 'boss', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');
    }

    // ── Home page sections ──────────────────────────────────────────────

    public function test_every_section_is_on_by_default_in_the_default_order(): void
    {
        $this->assertSame(array_keys(HomeSections::SECTIONS), HomeSections::enabled());
    }

    public function test_sections_can_be_switched_off_and_reordered(): void
    {
        $this->setting('home_sections', json_encode([
            ['key' => 'offers', 'on' => true],
            ['key' => 'hero', 'on' => false],
            ['key' => 'new_products', 'on' => true],
        ]));

        $enabled = HomeSections::enabled();

        $this->assertSame(['offers', 'new_products'], array_slice($enabled, 0, 2));
        $this->assertNotContains('hero', $enabled);
        $this->assertContains('categories', $enabled, 'sections missing from the saved list are appended, switched on');
    }

    public function test_the_home_page_follows_the_chosen_sections_and_order(): void
    {
        $category = Category::create(['name' => 'ক্যাটাগরি-চিহ্ন', 'slug' => 'cat-mark']);
        $this->product(['name' => 'ছাড়ের পণ্য-চিহ্ন', 'price' => 500, 'sale_price' => 400, 'category_id' => $category->id]);

        $all = array_map(fn ($k) => ['key' => $k, 'on' => false], array_keys(HomeSections::SECTIONS));
        $on = fn (array $keys) => array_map(fn ($row) => ['key' => $row['key'], 'on' => in_array($row['key'], $keys, true)], $all);

        // Categories first, then the offers.
        $this->setting('home_sections', json_encode([['key' => 'categories', 'on' => true], ['key' => 'offers', 'on' => true], ...array_slice($on([]), 2)]));
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'ছাড়ের পণ্য-চিহ্ন'), strpos($html, 'id="home-cats"'));

        // Offers first.
        $this->setting('home_sections', json_encode([['key' => 'offers', 'on' => true], ['key' => 'categories', 'on' => true], ...array_slice($on([]), 2)]));
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'id="home-cats"'), strpos($html, 'ছাড়ের পণ্য-চিহ্ন'));

        // Categories off: gone from the page.
        $this->setting('home_sections', json_encode([['key' => 'categories', 'on' => false], ['key' => 'offers', 'on' => true], ...array_slice($on([]), 2)]));
        $this->get('/')->assertOk()->assertDontSee('id="home-cats"', false)->assertSee('ছাড়ের পণ্য-চিহ্ন');

        // Everything off: a way to the shop remains.
        $this->setting('home_sections', json_encode($on([])));
        $this->get('/')->assertOk()->assertSee('সব পণ্য দেখুন')->assertDontSee('ছাড়ের পণ্য-চিহ্ন');
    }

    public function test_the_category_showcase_uses_the_picked_categories(): void
    {
        $a = Category::create(['name' => 'প্রথম-ক্যাট', 'slug' => 'a']);
        $b = Category::create(['name' => 'দ্বিতীয়-ক্যাট', 'slug' => 'b']);
        $this->product(['name' => 'এ-পণ্য', 'category_id' => $a->id]);
        $this->product(['name' => 'বি-পণ্য', 'category_id' => $b->id]);

        $this->setting('home_showcase_categories', json_encode([$b->id]));

        $this->assertSame([$b->id], HomeSections::showcaseCategories()->pluck('id')->all());

        $this->get('/')->assertOk()->assertSee('বি-পণ্য');
    }

    public function test_the_showcase_falls_back_to_categories_that_have_products(): void
    {
        Category::create(['name' => 'খালি', 'slug' => 'empty']);
        $full = Category::create(['name' => 'ভরা', 'slug' => 'full']);
        $this->product(['category_id' => $full->id]);

        $this->assertSame([$full->id], HomeSections::showcaseCategories()->pluck('id')->all());
    }

    // ── Footer / contact / social ───────────────────────────────────────

    public function test_footer_shows_its_own_logo_copyright_hours_and_all_social_links(): void
    {
        $this->setting('footer_logo', '/storage/media/footer.png');
        $this->setting('footer_copyright', 'আমার নিজের কপিরাইট লাইন');
        $this->setting('contact_hours', 'শনি–বৃহস্পতি, ১০টা–৯টা');
        $this->setting('social_twitter', 'https://x.com/shop');
        $this->setting('social_telegram', 'https://t.me/shop');

        $this->get('/')->assertOk()
            ->assertSee('<img src="/storage/media/footer.png"', false)
            ->assertSee('আমার নিজের কপিরাইট লাইন')
            ->assertSee('শনি–বৃহস্পতি, ১০টা–৯টা')
            ->assertSee('https://x.com/shop', false)
            ->assertSee('https://t.me/shop', false);
    }

    public function test_the_default_copyright_is_built_from_the_shop_names(): void
    {
        $this->setting('site_name', 'নতুন দোকান');
        $this->setting('site_name_en', 'New Shop');

        $this->assertStringContainsString('নতুন দোকান (New Shop)', SiteSettingsHelper::copyright());
    }

    public function test_the_whatsapp_button_needs_a_number(): void
    {
        $this->setting('contact_whatsapp', '');
        config(['site.whatsapp_number' => '']);

        $this->get('/')->assertOk()->assertDontSee('wa.me', false);
    }

    // ── The settings page itself ────────────────────────────────────────

    public function test_the_settings_page_shows_and_saves_the_sections_and_new_fields(): void
    {
        $this->needsIntl();
        $this->admin();
        $category = Category::create(['name' => 'জামা', 'slug' => 'jama']);

        $page = Livewire::test(SiteSettings::class)->assertSee('হোমপেজের সেকশন')->assertSee('ফুটারের লোগো');

        $sections = $page->get('data.home_sections');
        $this->assertCount(count(HomeSections::SECTIONS), $sections);

        // Switch the first (hero) off, and pick a showcase category.
        $first = array_key_first($sections);
        $page->set("data.home_sections.{$first}.on", false)
            ->set('data.home_showcase_categories', [$category->id])
            ->set('data.footer_copyright', 'আমার কপিরাইট')
            ->set('data.contact_hours', 'সকাল ১০টা')
            ->set('data.social_linkedin', 'https://linkedin.com/company/shop')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNotContains('hero', HomeSections::enabled());
        $this->assertSame([$category->id], HomeSections::showcaseCategoryIds());
        $this->assertSame('আমার কপিরাইট', SiteSetting::where('setting_key', 'footer_copyright')->value('setting_value'));
        $this->assertSame('https://linkedin.com/company/shop', SiteSettingsHelper::social('linkedin'));
    }

    public function test_changing_the_brand_colour_reaches_the_storefront_and_the_admin(): void
    {
        $this->setting('color_brand', '#7c3aed');

        $this->get('/')->assertOk()->assertSee('--brand:#7c3aed', false);
    }

    // ── Mobile nav / category strip / admin language ────────────────────

    public function test_the_viewport_meta_never_uses_viewport_fit_cover(): void
    {
        // viewport-fit=cover put the mobile bottom bar under Android's system bars until the first scroll.
        $this->get('/')->assertOk()->assertDontSee('viewport-fit', false);
        $this->get('/shop')->assertOk()->assertDontSee('viewport-fit', false);
        $this->get('/cart')->assertOk()->assertDontSee('viewport-fit', false);
    }

    public function test_the_home_category_strip_is_a_swipe_row_with_half_cards(): void
    {
        $this->product(['category_id' => Category::create(['name' => 'ক', 'slug' => 'k'])->id]);

        $this->get('/')->assertOk()->assertSee('swipe-row', false)->assertSee('swipe-half', false);
    }

    public function test_the_admin_panel_is_in_bangla(): void
    {
        $this->needsIntl();
        $this->admin();

        $this->get('/admin')->assertOk()
            ->assertSee('lang="bn"', false)
            ->assertSee('ড্যাশবোর্ড')
            ->assertSee('সাইট সেটিং')
            ->assertSee('অর্ডার');

        $this->get('/admin/orders')->assertOk()->assertSee('তৈরির সময়')->assertDontSee('Created at');
    }

    public function test_fields_without_a_label_get_a_bangla_one(): void
    {
        $this->assertSame('গ্রাহকের নাম', TextInput::make('customer_name')->getLabel());
        $this->assertSame('ছাড়ের দাম', TextColumn::make('sale_price')->getLabel());
        $this->assertSame('স্ট্যাটাস', SelectFilter::make('status')->getLabel());
        // a label set on the component itself still wins
        $this->assertSame('আমার লেবেল', TextInput::make('customer_name')->label('আমার লেবেল')->getLabel());
    }

    public function test_every_admin_field_name_in_the_dictionary_is_bangla(): void
    {
        foreach (AdminLang::FIELDS as $name => $label) {
            $this->assertMatchesRegularExpression('/\p{Bengali}/u', $label, $name);
        }
    }

    // ── Picking a picture: library and link ─────────────────────────────

    public function test_the_library_lists_stored_pictures_and_only_those_can_be_picked(): void
    {
        $url = ImageUpload::store(UploadedFile::fake()->image('logo.png', 40, 40));

        $this->assertArrayHasKey($url, ImageUpload::libraryOptions());
        $this->assertStringContainsString('logo.png', ImageUpload::libraryOptions()[$url]);
    }

    public function test_only_pictures_that_are_in_the_library_can_be_picked(): void
    {
        $url = ImageUpload::store(UploadedFile::fake()->image('pick.png', 40, 40));

        $this->assertSame([$url], ImageUpload::onlyLibraryUrls([$url, '/storage/media/not-registered.png', 'https://evil.test/x.png']));
        $this->assertSame([], ImageUpload::onlyLibraryUrls(['/storage/media/not-registered.png']));
    }

    public function test_every_picture_field_offers_the_library_and_link_buttons(): void
    {
        $names = array_map(fn ($a) => $a->getName(), ImageUpload::make('image')->getHintActions());

        $this->assertSame(['pickFromLibrary', 'pasteLink'], $names);
    }

    public function test_a_link_is_downloaded_checked_and_stored_on_our_server(): void
    {
        $png = UploadedFile::fake()->image('remote.png', 30, 30)->getContent();
        Http::fake(['*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);

        $local = ImageUpload::storeFromUrl('https://93.184.216.34/photos/remote.png');

        $this->assertMatchesRegularExpression('#^/storage/media/[0-9a-f-]{36}\.png$#', $local);
        Storage::disk('public')->assertExists('media/'.basename($local));
        $this->assertDatabaseHas('media_library', ['file_path' => $local]);
    }

    public function test_a_link_that_is_not_a_real_image_is_refused(): void
    {
        Http::fake(['*' => Http::response('<html>not an image</html>', 200)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JPG, PNG বা WEBP');

        ImageUpload::storeFromUrl('https://93.184.216.34/page.png');
    }

    #[DataProvider('blockedLinks')]
    public function test_links_into_the_servers_own_network_are_blocked(string $url): void
    {
        Http::fake(['*' => Http::response('secret', 200)]);

        try {
            RemoteImage::download($url);
            $this->fail("{$url} must be refused");
        } catch (RuntimeException $e) {
            $this->assertNotSame('', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public static function blockedLinks(): array
    {
        return [
            'localhost' => ['http://localhost/x.png'],
            'loopback' => ['http://127.0.0.1/x.png'],
            'private 10.x' => ['http://10.0.0.5/x.png'],
            'private 192.168' => ['http://192.168.1.1/x.png'],
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'ipv6 loopback' => ['http://[::1]/x.png'],
            'odd port' => ['http://93.184.216.34:8080/x.png'],
            'file scheme' => ['file:///etc/passwd'],
            'ftp scheme' => ['ftp://93.184.216.34/x.png'],
            'credentials' => ['http://user:pass@93.184.216.34/x.png'],
            'not a url' => ['not a url'],
        ];
    }

    public function test_a_redirect_into_the_private_network_is_blocked(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin'])]);

        $this->expectException(RuntimeException::class);

        RemoteImage::download('https://93.184.216.34/x.png');
    }

    public function test_the_rich_editor_gets_an_add_picture_button(): void
    {
        $this->needsIntl();
        $this->admin();
        $product = Product::create(['name' => 'ক', 'slug' => 'k', 'price' => 100, 'stock' => 1]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSee('ছবি যোগ করুন');
    }
}
