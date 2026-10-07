<?php

namespace Tests\Feature;

use App\Filament\Pages\MediaLibrary;
use App\Filament\Support\ImageUpload;
use App\Models\AdminUser;
use App\Models\Brand;
use App\Models\MediaLibrary as MediaItem;
use App\Support\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    use CreatesShopData;

    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        // Never touch the real public/storage during tests.
        $this->publicDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pub-'.uniqid();
        config(['media.public_path' => $this->publicDir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicDir);
        parent::tearDown();
    }

    public function test_an_image_is_saved_on_our_own_disk_with_a_relative_url(): void
    {
        $url = ImageUpload::store(UploadedFile::fake()->image('photo.png', 120, 120));

        $this->assertMatchesRegularExpression('#^/storage/media/[0-9a-f-]{36}\.png$#', $url);
        Storage::disk('public')->assertExists('media/'.basename($url));

        $row = MediaItem::first();
        $this->assertSame($url, $row->file_path);
        $this->assertSame('photo.png', $row->file_name);
        $this->assertSame('image/png', $row->mime_type);
    }

    public function test_jpg_and_webp_are_accepted_and_get_a_safe_extension(): void
    {
        if (! function_exists('imagejpeg')) {
            $this->markTestSkipped('GD without JPEG support: the test cannot generate a sample image.');
        }

        $jpg = ImageUpload::store(UploadedFile::fake()->image('a.jpeg', 50, 50));
        $this->assertStringEndsWith('.jpg', $jpg);

        if (function_exists('imagewebp')) {
            $webp = ImageUpload::store(UploadedFile::fake()->image('b.webp', 50, 50));
            $this->assertStringEndsWith('.webp', $webp);
        }
    }

    public function test_the_extension_comes_from_the_content_not_the_file_name(): void
    {
        $url = ImageUpload::store(UploadedFile::fake()->image('sneaky.php.png', 40, 40));

        $this->assertStringEndsWith('.png', $url);
        $this->assertStringNotContainsString('php', $url);
    }

    public function test_non_images_svg_and_fake_images_are_refused(): void
    {
        $bad = [
            UploadedFile::fake()->createWithContent('notes.png', 'this is not an image'),
            UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->createWithContent('shell.php', '<?php system($_GET["c"]);'),
            UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf'),
            UploadedFile::fake()->createWithContent('anim.gif', base64_decode('R0lGODlhAQABAAAAACw=')),
        ];

        foreach ($bad as $file) {
            try {
                ImageUpload::store($file);
                $this->fail($file->getClientOriginalName().' should have been refused');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('JPG, PNG বা WEBP', $e->getMessage());
            }
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, MediaItem::count());
    }

    public function test_oversized_images_are_refused(): void
    {
        config(['media.max_kb' => 1]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('অনেক বড়');

        ImageUpload::store(UploadedFile::fake()->image('big.png', 400, 400)->size(50));
    }

    public function test_the_upload_field_only_offers_the_allowed_types_and_size(): void
    {
        $field = ImageUpload::make('image');

        $this->assertEqualsCanonicalizing(['image/jpeg', 'image/png', 'image/webp'], $field->getAcceptedFileTypes());
        $this->assertSame((int) config('media.max_kb'), $field->getMaxSize());
    }

    public function test_files_are_mirrored_into_public_when_there_is_no_symlink(): void
    {
        $url = ImageUpload::store(UploadedFile::fake()->image('m.png', 30, 30));

        $this->assertTrue(Media::needsMirror());
        $this->assertFileExists($this->publicDir.'/media/'.basename($url));

        Media::unmirror('media/'.basename($url));
        $this->assertFileDoesNotExist($this->publicDir.'/media/'.basename($url));
    }

    public function test_media_link_copies_existing_uploads_when_symlinks_are_blocked(): void
    {
        Storage::disk('public')->put('media/old.png', UploadedFile::fake()->image('old.png', 20, 20)->getContent());
        Storage::disk('public')->put('private/secret.txt', 'x');

        $this->artisan('media:link', ['--copy' => true])->assertSuccessful();

        $this->assertFileExists($this->publicDir.'/media/old.png');
        $this->assertFileDoesNotExist($this->publicDir.'/private/secret.txt', 'only the media folder is published');
    }

    public function test_the_laravel_fallback_serves_images_but_nothing_else(): void
    {
        Storage::disk('public')->put('media/ok.png', UploadedFile::fake()->image('ok.png', 20, 20)->getContent());
        Storage::disk('public')->put('media/evil.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        Storage::disk('public')->put('media/note.txt', 'hello');

        $this->get('/storage/media/ok.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/storage/media/evil.svg')->assertNotFound();
        $this->get('/storage/media/note.txt')->assertNotFound();
        $this->get('/storage/media/missing.png')->assertNotFound();
        $this->get('/storage/media/..%2F..%2F.env')->assertNotFound();
    }

    public function test_absolute_urls_for_feeds_and_social_tags(): void
    {
        $this->assertSame(url('/storage/media/a.png'), Media::absolute('/storage/media/a.png'));
        $this->assertSame('https://cdn.test/a.png', Media::absolute('https://cdn.test/a.png'));
        $this->assertSame('https://cdn.test/a.png', Media::absolute('//cdn.test/a.png'));
        $this->assertNull(Media::absolute(''));
    }

    public function test_the_product_feed_and_social_tags_use_full_urls_for_local_images(): void
    {
        $product = $this->product(['image' => '/storage/media/p.png']);
        $this->setting('og_image', '/storage/media/og.png');

        $this->get('/api/feed/facebook')->assertSee('<g:image_link>'.url('/storage/media/p.png').'</g:image_link>', false);
        $this->get('/')->assertSee('<meta property="og:image" content="'.url('/storage/media/og.png').'">', false);
        $this->assertNotNull($product);
    }

    public function test_media_library_lists_files_and_refuses_to_delete_one_in_use(): void
    {
        $this->actingAs(AdminUser::create(['username' => 'a', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');

        $used = ImageUpload::store(UploadedFile::fake()->image('used.png', 20, 20));
        $free = ImageUpload::store(UploadedFile::fake()->image('free.png', 20, 20));
        Brand::create(['name' => 'B', 'logo' => $used]);

        $page = new MediaLibrary;
        $this->assertCount(2, $page->getFiles());
        $this->assertSame('used.png', $page->getFiles()->firstWhere('url', $used)['name']);

        $page->deleteFile(basename($used));
        Storage::disk('public')->assertExists('media/'.basename($used));

        $page->deleteFile(basename($free));
        Storage::disk('public')->assertMissing('media/'.basename($free));
        $this->assertSame(0, MediaItem::where('file_path', $free)->count());
    }

    public function test_media_library_rejects_path_tricks(): void
    {
        $this->actingAs(AdminUser::create(['username' => 'a', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        (new MediaLibrary)->deleteFile('../.env');
    }

    public function test_remote_images_can_be_localized_and_failures_are_reported(): void
    {
        $png = UploadedFile::fake()->image('r.png', 20, 20)->getContent();
        Http::fake([
            'cdn.test/good.png' => Http::response($png, 200),
            'cdn.test/missing.png' => Http::response('nope', 404),
            'cdn.test/fake.png' => Http::response('<html>not an image</html>', 200),
        ]);

        $brand = Brand::create(['name' => 'B', 'logo' => 'https://cdn.test/good.png']);
        $missing = Brand::create(['name' => 'M', 'logo' => 'https://cdn.test/missing.png']);
        $fake = Brand::create(['name' => 'F', 'logo' => 'https://cdn.test/fake.png']);
        $own = Brand::create(['name' => 'O', 'logo' => url('/storage/media/already.png')]);

        $this->artisan('media:localize-remote', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('https://cdn.test/good.png', $brand->fresh()->logo, 'dry run changes nothing');

        $this->artisan('media:localize-remote')->assertFailed(); // two could not be fetched

        $this->assertMatchesRegularExpression('#^/storage/media/[0-9a-f-]{36}\.png$#', $brand->fresh()->logo);
        $this->assertSame('https://cdn.test/missing.png', $missing->fresh()->logo, 'failed downloads are left as they were');
        $this->assertSame('https://cdn.test/fake.png', $fake->fresh()->logo);
        $this->assertSame(url('/storage/media/already.png'), $own->fresh()->logo, 'our own URLs are untouched');
        Storage::disk('public')->assertExists('media/'.basename($brand->fresh()->logo));
    }
}
