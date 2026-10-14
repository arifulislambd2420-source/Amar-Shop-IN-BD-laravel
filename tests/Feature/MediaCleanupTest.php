<?php

namespace Tests\Feature;

use App\Filament\Pages\MediaLibrary;
use App\Filament\Support\ImageUpload;
use App\Models\AdminUser;
use App\Models\Banner;
use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\LandingPage;
use App\Models\MediaLibrary as MediaItem;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\SiteSetting;
use App\Support\MediaDedupe;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/** Media library: one copy per picture, merging old duplicates, and deleting a picture that is in use. */
class MediaCleanupTest extends TestCase
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

    private function png(int $size = 20): string
    {
        return UploadedFile::fake()->image('x.png', $size, $size)->getContent();
    }

    /** A picture file already on disk (as an older upload would be), with or without a hash. */
    private function oldFile(string $name, string $bytes, int $age, bool $row = true): string
    {
        Storage::disk('public')->put('media/'.$name, $bytes);
        $url = '/storage/media/'.$name;

        if ($row) {
            MediaItem::create(['file_name' => $name, 'file_path' => $url, 'mime_type' => 'image/png', 'file_size' => strlen($bytes), 'created_at' => now()->subMinutes($age)]);
        }

        return $url;
    }

    // ── one copy per picture ────────────────────────────────────────────

    public function test_the_same_picture_uploaded_twice_is_stored_once(): void
    {
        $a = ImageUpload::store(UploadedFile::fake()->image('first.png', 20, 20));
        $again = ImageUpload::store(UploadedFile::fake()->image('renamed-copy.png', 20, 20));
        $other = ImageUpload::store(UploadedFile::fake()->image('other.png', 40, 40));

        $this->assertSame($a, $again, 'the second upload reuses the first');
        $this->assertNotSame($a, $other);
        $this->assertCount(2, Storage::disk('public')->files('media'));
        $this->assertSame(2, MediaItem::count());
        $this->assertSame(hash('sha256', Storage::disk('public')->get('media/'.basename($a))), MediaItem::where('file_path', $a)->value('file_hash'));
    }

    public function test_an_upload_is_matched_with_an_older_picture_that_has_no_hash_yet(): void
    {
        $bytes = $this->png(25);
        $old = $this->oldFile('legacy-photo.png', $bytes, 100);
        $this->assertNull(MediaItem::first()->file_hash);

        $reused = ImageUpload::store(UploadedFile::fake()->createWithContent('new-name.png', $bytes));

        $this->assertSame($old, $reused);
        $this->assertCount(1, Storage::disk('public')->files('media'));
    }

    public function test_a_picture_whose_file_is_gone_is_stored_again(): void
    {
        $a = ImageUpload::store(UploadedFile::fake()->image('a.png', 20, 20));
        Storage::disk('public')->delete('media/'.basename($a));

        $b = ImageUpload::store(UploadedFile::fake()->image('a.png', 20, 20));

        $this->assertNotSame($a, $b);
        Storage::disk('public')->assertExists('media/'.basename($b));
    }

    // ── merging the duplicates that already exist ───────────────────────

    private function duplicates(): array
    {
        $bytes = $this->png(20);
        $keep = $this->oldFile('aaa-original.png', $bytes, 300);
        $dup1 = $this->oldFile('bbb-copy.png', $bytes, 200);
        $dup2 = $this->oldFile('ccc-copy.png', $bytes, 100, row: false); // no library row at all
        $unique = $this->oldFile('ddd-unique.png', $this->png(33), 50);

        return [$keep, $dup1, $dup2, $unique];
    }

    public function test_merging_keeps_the_oldest_points_every_use_at_it_and_deletes_the_copies(): void
    {
        [$keep, $dup1, $dup2, $unique] = $this->duplicates();

        $product = $this->product(['image' => $dup1, 'description' => '<p>Hi</p><p><img src="'.$dup2.'" alt=""></p>']);
        ProductImage::create(['product_id' => $product->id, 'url' => $dup2, 'sort_order' => 1]);
        $brand = Brand::create(['name' => 'B', 'logo' => 'https://shop.test'.$dup1]); // a full URL with the same path
        $blog = Blog::create(['title' => 'T', 'slug' => 't', 'cover' => $dup2, 'content' => '<img src="'.$dup1.'">']);
        $page = LandingPage::create([
            'title' => 'L', 'slug' => 'l', 'headline' => 'L', 'template' => 'green', 'button_text' => 'x',
            'hero_image' => $dup1, 'gallery' => [$dup1, $unique, $dup2],
            'blocks' => [['type' => 'hero', 'data' => ['images' => [$dup2], 'image' => $dup1]]],
            'packages' => [['label' => 'P', 'image' => $dup2, 'price' => 1, 'product_id' => $product->id]],
        ]);
        SiteSetting::create(['setting_key' => 'site_logo', 'setting_value' => $dup1]);

        $plan = MediaDedupe::plan();
        $this->assertCount(1, $plan);
        $this->assertSame($keep, $plan[0]['keep']);
        $this->assertEqualsCanonicalizing([$dup1, $dup2], $plan[0]['copies']);

        $stats = MediaDedupe::merge();

        $this->assertSame(2, $stats['removed']);
        $this->assertSame([], $stats['kept_in_use']);
        $this->assertSame($keep, $product->fresh()->image);
        $this->assertStringContainsString($keep, $product->fresh()->description);
        $this->assertStringNotContainsString('copy', $product->fresh()->description);
        $this->assertSame($keep, ProductImage::first()->url);
        $this->assertSame('https://shop.test'.$keep, $brand->fresh()->logo);
        $this->assertSame($keep, $blog->fresh()->cover);
        $this->assertStringContainsString($keep, $blog->fresh()->content);
        $this->assertSame([$keep, $unique, $keep], $page->fresh()->gallery);
        $this->assertSame($keep, $page->fresh()->hero_image);
        $this->assertSame([$keep], $page->fresh()->blocks[0]['data']['images']);
        $this->assertSame($keep, $page->fresh()->packages[0]['image']);
        $this->assertSame($keep, SiteSetting::where('setting_key', 'site_logo')->value('setting_value'));

        Storage::disk('public')->assertExists('media/aaa-original.png');
        Storage::disk('public')->assertExists('media/ddd-unique.png');
        Storage::disk('public')->assertMissing('media/bbb-copy.png');
        Storage::disk('public')->assertMissing('media/ccc-copy.png');
        $this->assertSame(['/storage/media/aaa-original.png', '/storage/media/ddd-unique.png'], MediaItem::orderBy('file_path')->pluck('file_path')->all());
        $this->assertNotNull(MediaItem::where('file_path', $keep)->value('file_hash'));

        // Nothing left to merge.
        $this->assertSame([], MediaDedupe::plan());
    }

    public function test_the_command_dry_run_changes_nothing_and_the_real_run_merges(): void
    {
        [$keep, $dup1] = $this->duplicates();
        $product = $this->product(['image' => $dup1]);

        Artisan::call('media:dedupe', ['--dry-run' => true]);
        $this->assertCount(4, Storage::disk('public')->files('media'));
        $this->assertSame($dup1, $product->fresh()->image);

        $this->assertSame(0, Artisan::call('media:dedupe', ['--force' => true]));
        $this->assertCount(2, Storage::disk('public')->files('media'));
        $this->assertSame($keep, $product->fresh()->image);
    }

    public function test_demo_pictures_are_never_merged(): void
    {
        $bytes = $this->png(20);
        $this->oldFile('demo-lp-green-gold.png', $bytes, 100);
        $this->oldFile('demo-lp-green-silver.png', $bytes, 90);
        $this->oldFile('real-one.png', $bytes, 80);

        $this->assertSame([], MediaDedupe::plan());
    }

    public function test_the_media_library_has_a_merge_button(): void
    {
        $this->actingAs(AdminUser::create(['username' => 'a', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');
        [$keep] = $this->duplicates();
        $product = $this->product(['image' => '/storage/media/bbb-copy.png']);

        Livewire::test(MediaLibrary::class)->callAction('mergeDuplicates')->assertNotified();

        $this->assertSame($keep, $product->fresh()->image);
        $this->assertCount(2, Storage::disk('public')->files('media'));
    }

    // ── deleting a picture that is in use ───────────────────────────────

    private function usedEverywhere(): array
    {
        $url = $this->oldFile('shared-photo.png', $this->png(20), 10);
        $other = $this->oldFile('other-photo.png', $this->png(31), 5);

        $product = $this->product(['name' => 'Shirt', 'image' => $url, 'description' => '<p>Text</p><p><img src="'.$url.'" alt="x"></p>']);
        ProductImage::create(['product_id' => $product->id, 'url' => $url, 'sort_order' => 1]);
        ProductImage::create(['product_id' => $product->id, 'url' => $other, 'sort_order' => 2]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Red', 'price' => 5, 'stock' => 2, 'image' => $url]);
        $brand = Brand::create(['name' => 'Acme', 'logo' => $url]);
        $category = Category::create(['name' => 'Tops', 'slug' => 'tops', 'icon' => $url]);
        $blog = Blog::create(['title' => 'Post', 'slug' => 'post', 'cover' => $url, 'content' => '<p>Hello</p><img src="'.$url.'">', 'published_at' => now()->subDay()]);
        $banner = Banner::create(['image' => $url, 'position' => 'hero', 'active' => true]);
        $page = LandingPage::create([
            'title' => 'Landing', 'slug' => 'landing', 'headline' => 'L', 'template' => 'green', 'button_text' => 'x', 'is_active' => true,
            'product_id' => $product->id, 'hero_image' => $url, 'og_image' => $url, 'gallery' => [$url, $other],
            'blocks' => [
                ['type' => 'hero', 'data' => ['headline' => 'Hi', 'images' => [$url, $other], 'image' => $url]],
                ['type' => 'gallery', 'data' => ['images' => [$url]]],
                ['type' => 'features', 'data' => ['items' => [['title' => 'A', 'icon' => $url]]]],
                ['type' => 'order_form', 'data' => []],
            ],
            'packages' => [['label' => 'P', 'image' => $url, 'price' => 10, 'product_id' => $product->id]],
        ]);
        SiteSetting::create(['setting_key' => 'site_logo', 'setting_value' => $url]);

        return compact('url', 'other', 'product', 'variant', 'brand', 'category', 'blog', 'banner', 'page');
    }

    public function test_deleting_asks_first_and_lists_where_the_picture_is_used(): void
    {
        $this->actingAs(AdminUser::create(['username' => 'a', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');
        $x = $this->usedEverywhere();

        $lw = Livewire::test(MediaLibrary::class)->call('askDelete', 'shared-photo.png');

        $places = $lw->get('pending')['places'];
        foreach (['Product "Shirt"', 'Brand "Acme"', 'Category "Tops"', 'Blog post "Post"', 'Landing page "Landing"', 'Site setting "site_logo"'] as $expected) {
            $this->assertContains($expected, $places);
        }
        Storage::disk('public')->assertExists('media/shared-photo.png'); // only asked so far

        $lw->call('cancelDelete')->assertSet('pending', null);
        Storage::disk('public')->assertExists('media/shared-photo.png');
        $this->assertSame($x['url'], $x['product']->fresh()->image);

        // The plain delete (no confirmation) still refuses a picture that is in use.
        $this->assertFalse((new MediaLibrary)->deleteFile('shared-photo.png'));
        Storage::disk('public')->assertExists('media/shared-photo.png');
    }

    public function test_confirming_deletes_the_file_and_empties_every_place_that_used_it(): void
    {
        $this->actingAs(AdminUser::create(['username' => 'a', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');
        $x = $this->usedEverywhere();

        Livewire::test(MediaLibrary::class)->call('askDelete', 'shared-photo.png')->call('confirmDelete')->assertSet('pending', null);

        Storage::disk('public')->assertMissing('media/shared-photo.png');
        Storage::disk('public')->assertExists('media/other-photo.png');
        $this->assertSame(0, MediaItem::where('file_path', $x['url'])->count());

        $this->assertSame('', $x['product']->fresh()->image);
        $this->assertStringNotContainsString('<img', $x['product']->fresh()->description);
        $this->assertStringContainsString('Text', $x['product']->fresh()->description);
        $this->assertSame([$x['other']], ProductImage::pluck('url')->all());
        $this->assertNull($x['variant']->fresh()->image);
        $this->assertNull($x['brand']->fresh()->logo);
        $this->assertNull($x['category']->fresh()->icon);
        $this->assertNull($x['blog']->fresh()->cover);
        $this->assertStringNotContainsString('<img', $x['blog']->fresh()->content);
        $this->assertSame('', $x['banner']->fresh()->image);
        $this->assertFalse($x['banner']->fresh()->active, 'a banner without its picture is switched off');

        $page = $x['page']->fresh();
        $this->assertNull($page->hero_image);
        $this->assertNull($page->og_image);
        $this->assertSame([$x['other']], $page->gallery);
        $this->assertSame([$x['other']], $page->blocks[0]['data']['images']);
        $this->assertNull($page->blocks[0]['data']['image']);
        $this->assertSame([], $page->blocks[1]['data']['images']);
        $this->assertNull($page->blocks[2]['data']['items'][0]['icon']);
        $this->assertSame('A', $page->blocks[2]['data']['items'][0]['title']);
        $this->assertNull($page->packages[0]['image']);
        $this->assertSame('P', $page->packages[0]['label']);
        $this->assertSame('', SiteSetting::where('setting_key', 'site_logo')->value('setting_value'));
    }

    public function test_after_deleting_a_used_picture_no_page_breaks_and_none_shows_an_empty_image(): void
    {
        $this->actingAs(AdminUser::create(['username' => 'a', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');
        $x = $this->usedEverywhere();
        $this->product(['name' => 'Second', 'slug' => 'second', 'category_id' => $x['category']->id, 'brand_id' => $x['brand']->id]);

        Livewire::test(MediaLibrary::class)->call('askDelete', 'shared-photo.png')->call('confirmDelete');

        foreach (['/', '/shop', '/offers', '/brands', '/blog', '/blog/post', '/product/'.$x['product']->slug, '/lp/landing', '/shop?brand[]='.$x['brand']->id, '/cart', '/about', '/api/feed/facebook'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('shared-photo', $html, "{$url} still points at the deleted picture");
            $this->assertDoesNotMatchRegularExpression('/<img[^>]*\ssrc=""/', $html, "{$url} has an empty <img>");
        }
    }
}
