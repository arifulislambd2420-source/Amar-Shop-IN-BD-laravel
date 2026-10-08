<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MediaLibrary;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Media;
use App\Support\StorefrontCache;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Sample clothing shop: 5 categories x 4 products, sizes S/M/L/XL with stock,
 * some products on sale, one placeholder picture per product.
 *
 * Not part of DatabaseSeeder — run it on purpose:
 *   php artisan db:seed --class=DemoClothingSeeder --force
 *
 * Every row it creates is marked is_demo = true (categories and products;
 * variants go with their product) and every picture is stored as
 * media/demo-<slug>.png, so `php artisan demo:remove` can delete exactly the
 * demo data. Real rows are never changed: if a slug is already taken by a row
 * that is not demo data, that category / product is skipped.
 *
 * Safe to run again: demo rows that already exist are left as they are.
 *
 * The product list lives in database/seeders/demo-clothing/catalog.php, the
 * pictures next to it as <product slug>.png.
 */
class DemoClothingSeeder extends Seeder
{
    public const SIZES = ['S', 'M', 'L', 'XL'];

    public function run(): void
    {
        if (! Schema::hasColumn('products', 'is_demo') || ! Schema::hasColumn('categories', 'is_demo')) {
            throw new RuntimeException('আগে `php artisan migrate --force` চালান — ডেমো ডাটা চিহ্নিত করার কলাম এখনও নেই।');
        }

        $dir = __DIR__.'/demo-clothing';
        $catalog = require $dir.'/catalog.php';
        $created = 0;
        $skipped = 0;

        foreach ($catalog as $cat) {
            $category = Category::where('slug', $cat['slug'])->first();

            if ($category && ! $category->is_demo) {
                $this->say('warn', "ক্যাটাগরি স্লাগ '{$cat['slug']}' আসল ডাটায় আছে — এই ক্যাটাগরির ডেমো প্রোডাক্ট বাদ দেওয়া হলো।");
                $skipped += count($cat['products']);

                continue;
            }

            if (! $category) {
                $category = new Category(['name' => $cat['name'], 'slug' => $cat['slug'], 'icon' => $cat['icon']]);
                $category->forceFill(['is_demo' => true])->save();
            }

            foreach ($cat['products'] as $i => $p) {
                if (Product::withTrashed()->where('slug', $p['slug'])->exists()) {
                    $skipped++;

                    continue;
                }

                $sku = 'DEMO-'.$cat['sku'].'-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);

                if (Product::withTrashed()->where('sku', $sku)->exists()) {
                    $skipped++;

                    continue;
                }

                DB::transaction(function () use ($p, $category, $sku, $dir) {
                    $price = (float) ($p['sale'] ?? $p['price']);

                    $product = new Product([
                        'name' => $p['name'],
                        'slug' => $p['slug'],
                        'description' => $p['description'],
                        'price' => $p['price'],
                        'sale_price' => $p['sale'],
                        'image' => $this->storePicture($dir.'/'.$p['slug'].'.png', $p['slug']),
                        'category_id' => $category->id,
                        'stock' => array_sum($p['stock']),
                        'is_active' => true,
                        'status' => 'published',
                        'sku' => $sku,
                        'tags' => 'demo',
                    ]);
                    $product->forceFill(['is_demo' => true])->save();

                    // The cart charges the variant's own price, so on a
                    // discounted product the variants carry the sale price.
                    foreach (self::SIZES as $n => $size) {
                        ProductVariant::create([
                            'product_id' => $product->id,
                            'label' => $size,
                            'price' => $price,
                            'stock' => $p['stock'][$n],
                        ]);
                    }
                });

                $created++;
            }
        }

        StorefrontCache::flush();

        $this->say('info', "ডেমো ক্লোদিং ডাটা: {$created} টি প্রোডাক্ট যোগ হয়েছে".($skipped ? ", {$skipped} টি আগে থেকেই ছিল/বাদ গেছে" : '').'।');
        $this->say('line', 'পরে মুছতে: php artisan demo:remove');
    }

    /** Copy a bundled placeholder picture into public storage; returns its site-relative URL. */
    private function storePicture(string $source, string $slug): string
    {
        if (! is_file($source)) {
            throw new RuntimeException("ডেমো ছবি পাওয়া যায়নি: {$source}");
        }

        $name = $slug.'.png';
        $path = Storage::disk(config('media.disk'))->putFileAs(config('media.directory'), new File($source), $name);

        if (! $path) {
            throw new RuntimeException('ছবি সার্ভারে লেখা যায়নি (storage ফোল্ডারের permission দেখুন)।');
        }

        Media::mirror($path);
        $url = Media::urlFor($path);

        MediaLibrary::firstOrCreate(['file_path' => $url], [
            'file_name' => $name,
            'mime_type' => 'image/png',
            'file_size' => (int) filesize($source),
        ]);

        return $url;
    }

    private function say(string $level, string $message): void
    {
        $this->command?->{$level}($message);
    }
}
