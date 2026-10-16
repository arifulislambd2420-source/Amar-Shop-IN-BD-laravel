<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\ImageUpload;
use App\Models\AdminUser;
use App\Models\MediaLibrary;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/** Several pictures per product, set from the product form itself (with or without colours). */
class ProductGalleryTest extends TestCase
{
    use CreatesShopData;

    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }

        $this->setting('admin_locale', 'bn');
        app()->setLocale('bn');
        Storage::fake('public');
        $this->publicDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pub-'.uniqid();
        config(['media.public_path' => $this->publicDir]);

        $this->actingAs(AdminUser::create(['username' => 'boss', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicDir);
        parent::tearDown();
    }

    public function test_the_gallery_is_in_the_product_form_and_the_old_one_by_one_tab_is_gone(): void
    {
        $this->assertCount(1, ProductResource::getRelations());

        $product = $this->product();
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSee('ছবির গ্যালারি')
            ->assertFormFieldExists('gallery');

        Livewire::test(CreateProduct::class)->assertFormFieldExists('gallery');
    }

    public function test_many_pictures_save_in_order_on_create_and_reorder_on_edit(): void
    {
        $urls = collect(range(1, 5))->map(fn ($i) => ImageUpload::store(UploadedFile::fake()->image("p{$i}.png", 10 + $i, 10 + $i)))->all();

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'গ্যালারি পণ্য', 'slug' => 'gallery-product', 'status' => 'published', 'price' => 100, 'stock' => 3, 'is_active' => true, 'gallery' => $urls])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'gallery-product')->firstOrFail();
        $this->assertSame($urls, $product->galleryUrls());
        $this->assertSame([0, 1, 2, 3, 4], $product->images()->orderBy('sort_order')->pluck('sort_order')->all());

        // Open it again: the saved pictures are loaded; drag the last one to the front, drop the middle one.
        $reordered = [$urls[4], $urls[0], $urls[1], $urls[3]];
        $keptRow = ProductImage::where('url', $urls[0])->first();
        $keptRow->update(['alt' => 'front']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormSet(fn (array $state) => array_values($state['gallery']) === $urls)
            ->fillForm(['gallery' => $reordered])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($reordered, $product->fresh()->galleryUrls());
        $this->assertSame('front', ProductImage::where('url', $urls[0])->value('alt'), 'a picture that stays keeps its row');
        // Removing from the gallery only unlinks; the file is still in the Media Library.
        $this->assertSame(1, MediaLibrary::where('file_path', $urls[2])->count());
    }

    public function test_the_gallery_works_for_a_product_with_colour_variants_and_all_pictures_show(): void
    {
        $product = $this->product(['name' => 'রঙিন শার্ট', 'slug' => 'colour-shirt', 'image' => '/storage/media/main.png']);
        ProductVariant::create(['product_id' => $product->id, 'label' => 'লাল', 'color' => 'লাল', 'price' => 500, 'stock' => 4, 'image' => '/storage/media/red.png']);
        ProductVariant::create(['product_id' => $product->id, 'label' => 'নীল', 'color' => 'নীল', 'price' => 500, 'stock' => 4, 'image' => '/storage/media/blue.png']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['gallery' => ['/storage/media/g1.png', '/storage/media/g2.png', '/storage/media/g3.png']])
            ->call('save')
            ->assertHasNoFormErrors();

        $html = $this->get('/product/colour-shirt')->assertOk()->getContent();

        $order = array_map(fn ($u) => strpos($html, 'src="'.$u.'"'), [
            '/storage/media/main.png', '/storage/media/g1.png', '/storage/media/g2.png', '/storage/media/g3.png',
            '/storage/media/red.png', '/storage/media/blue.png',
        ]);
        $this->assertNotContains(false, $order, 'every picture is on the product page');
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order, 'main, then the gallery in order, then the colour pictures');
    }

    public function test_pictures_can_be_picked_from_the_media_library_several_at_once(): void
    {
        $a = ImageUpload::store(UploadedFile::fake()->image('a.png', 20, 20));
        $b = ImageUpload::store(UploadedFile::fake()->image('b.png', 21, 21));
        $product = $this->product();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction(TestAction::make("pickFromLibrary")->schemaComponent('gallery'), ["urls" => [$a, $b]])
            ->call('save');

        $this->assertSame([$a, $b], $product->fresh()->galleryUrls());
    }

    public function test_several_links_can_be_pasted_at_once(): void
    {
        $f1 = UploadedFile::fake()->image('1.png', 11, 11);
        $one = file_get_contents($f1->getRealPath());
        $f2 = UploadedFile::fake()->image('2.png', 12, 12);
        $two = file_get_contents($f2->getRealPath());
        Http::fake([
            '*one.png' => Http::response($one, 200, ['Content-Type' => 'image/png']),
            '*two.png' => Http::response($two, 200, ['Content-Type' => 'image/png']),
        ]);

        $product = $this->product();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction(TestAction::make('pasteLink')->schemaComponent('gallery'), ['url' => "https://93.184.216.34/one.png\nhttps://93.184.216.34/two.png"])
            ->call('save');

        $this->assertCount(2, $product->fresh()->galleryUrls());
    }
}
