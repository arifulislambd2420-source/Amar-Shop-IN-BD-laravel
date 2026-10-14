<?php

namespace Database\Seeders;

use App\Models\LandingPage;
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
 * Six sample landing pages — one for every page template (green, purple,
 * cream, template-1, -2, -3) — each with its own sample product that has 3-4
 * colours/models (own picture, price and stock per colour).
 *
 * Not part of DatabaseSeeder — run it on purpose:
 *   php artisan db:seed --class=DemoLandingSeeder --force
 *
 * Everything it creates is marked is_demo (products, landing pages) and its
 * pictures are stored as media/demo-lp-*.png, so `php artisan demo:remove`
 * deletes exactly this sample data. Real rows are never changed: a slug that
 * is already taken is skipped. Safe to run again.
 *
 * Page data lives in database/seeders/demo-landing/catalog.php, the pictures
 * next to it (made by generate-images.php).
 */
class DemoLandingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['products', 'landing_pages'] as $table) {
            if (! Schema::hasColumn($table, 'is_demo')) {
                throw new RuntimeException('আগে `php artisan migrate --force` চালান — ডেমো ডাটা চিহ্নিত করার কলাম এখনও নেই।');
            }
        }

        $dir = __DIR__.'/demo-landing';
        $created = 0;
        $skipped = 0;

        foreach (require $dir.'/catalog.php' as $i => $page) {
            $productSlug = 'demo-product-'.substr($page['slug'], strlen('demo-'));

            if (LandingPage::where('slug', $page['slug'])->exists() || Product::withTrashed()->where('slug', $productSlug)->exists()) {
                $skipped++;

                continue;
            }

            DB::transaction(function () use ($page, $productSlug, $dir, $i) {
                $pictures = [];
                foreach ($page['colors'] as $color) {
                    $pictures[$color['key']] = $this->storePicture($dir.'/'.$page['slug'].'-'.$color['key'].'.png', $page['slug'].'-'.$color['key']);
                }
                $first = reset($pictures);

                $product = new Product([
                    'name' => $page['product'],
                    'slug' => $productSlug,
                    'description' => 'ডেমো পণ্য — ল্যান্ডিং পেজ দেখানোর জন্য। ডেমো ডাটা মুছলে এটিও মুছে যাবে।',
                    'price' => $page['price'],
                    'image' => $first,
                    'stock' => 0,
                    'is_active' => true,
                    'status' => 'published',
                    'sku' => 'DEMO-LP-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'tags' => 'demo',
                ]);
                $product->forceFill(['is_demo' => true])->save();

                // The variants carry the selling price; product stock follows them.
                foreach ($page['colors'] as $color) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'color' => $color['name'],
                        'color_hex' => $color['hex'],
                        'image' => $pictures[$color['key']],
                        'price' => $color['price'],
                        'stock' => $color['stock'],
                    ]);
                }

                $landing = new LandingPage(array_merge([
                    'title' => $page['title'],
                    'slug' => $page['slug'],
                    'template' => $page['template'],
                    'product_id' => $product->id,
                    'button_text' => 'এখনই অর্ডার করুন',
                    'is_active' => true,
                    'seo_title' => $page['product'].' — ডেমো',
                    'seo_description' => 'ডেমো ল্যান্ডিং পেজ: '.$page['product'],
                ], LandingPage::isLegacyTemplate($page['template'])
                    ? $this->legacyContent($page, array_values($pictures))
                    : ['headline' => $page['product'], 'hero_image' => $first, 'blocks' => $this->blocks($page, array_values($pictures))]));
                $landing->forceFill(['is_demo' => true])->save();
            });

            $created++;
        }

        StorefrontCache::flush();

        $this->say('info', "ডেমো ল্যান্ডিং পেজ: {$created} টি যোগ হয়েছে".($skipped ? ", {$skipped} টি আগে থেকেই ছিল" : '').'।');
        $this->say('line', 'পেজগুলো: /lp/demo-lp-green, -purple, -cream, -t1, -t2, -t3 — পরে মুছতে: php artisan demo:remove');
    }

    /** @return list<array{icon: string, title: string, description: string}> */
    private function promises(): array
    {
        return [
            ['icon' => '✅', 'title' => '১০০% আসল পণ্য', 'description' => 'যেমন ছবিতে দেখছেন, তেমনই পাবেন।'],
            ['icon' => '🚚', 'title' => 'দ্রুত ডেলিভারি', 'description' => 'ঢাকায় ১–২ দিন, সারা দেশে ২–৪ দিন।'],
            ['icon' => '🔄', 'title' => '৭ দিনের রিটার্ন', 'description' => 'পছন্দ না হলে ফেরত বা এক্সচেঞ্জ।'],
            ['icon' => '💵', 'title' => 'ক্যাশ অন ডেলিভারি', 'description' => 'পণ্য হাতে পেয়ে টাকা দিন।'],
        ];
    }

    /** Page-builder content (green / purple / cream). */
    private function blocks(array $page, array $pictures): array
    {
        $name = $page['product'];

        return [
            ['type' => 'hero', 'data' => [
                'headline' => $name,
                'offer_line' => 'বিশেষ ছাড়ে — স্টক থাকা পর্যন্ত',
                'description' => 'ডেমো ল্যান্ডিং পেজ। এখানকার লেখা, ছবি আর দাম অ্যাডমিন থেকে বদলে নিজের পণ্যের পেজ বানান।',
                'badge_text' => 'সীমিত সময়ের অফার',
                'button_text' => 'অর্ডার করতে চাই',
                'images' => $pictures,
                'bullets' => ['সব রঙে আলাদা দাম ও ছবি', 'ক্যাশ অন ডেলিভারি', '৭ দিনের রিটার্ন সুবিধা'],
            ]],
            ['type' => 'price', 'data' => [
                'label' => 'আজকের অফার',
                'heading' => 'সেরা দামে নিন',
                'note' => 'ক্যাশ অন ডেলিভারি — আগে কোনো টাকা লাগবে না',
                'button_text' => 'অর্ডার করুন',
            ]],
            ['type' => 'features', 'data' => [
                'heading' => 'কেন আমাদের পণ্যটি কিনবেন?',
                'items' => [
                    ['title' => 'প্রিমিয়াম মান', 'description' => 'যত্ন নিয়ে বাছাই করা, টেকসই।'],
                    ['title' => 'আকর্ষণীয় ডিজাইন', 'description' => 'সব পোশাক ও সময়ের সাথে মানানসই।'],
                    ['title' => 'সাশ্রয়ী দাম', 'description' => 'বাজারের চেয়ে কম দামে।'],
                    ['title' => 'অনেক রঙের পছন্দ', 'description' => 'আপনার পছন্দের রংটি বেছে নিন।'],
                ],
            ]],
            ['type' => 'models', 'data' => [
                'label' => 'রং / মডেল',
                'heading' => 'আপনার পছন্দের রং বেছে নিন',
                'description' => 'প্রতিটি রঙের আলাদা ছবি, দাম ও স্টক।',
                'button_text' => 'অর্ডার করুন',
            ]],
            ['type' => 'trust', 'data' => ['heading' => 'কেন আমাদের উপর ভরসা করবেন?', 'items' => $this->promises()]],
            ['type' => 'order_form', 'data' => [
                'pill' => 'কোনো অগ্রিম টাকা লাগবে না',
                'heading' => 'সঠিক তথ্য দিয়ে নিচের ফর্মটি পূরণ করুন',
                'subtext' => 'রং বাছুন, নাম-ঠিকানা-ফোন দিন — আমরা কল করে কনফার্ম করব।',
                'button_text' => 'অর্ডার কনফার্ম করুন',
                'note' => 'এটি একটি ডেমো পেজ।',
            ]],
        ];
    }

    /** Fixed fields of the older templates (template-1/2/3). */
    private function legacyContent(array $page, array $pictures): array
    {
        return [
            'headline' => $page['product'],
            'sub_headline' => 'ডেমো ল্যান্ডিং পেজ — সব রঙে আলাদা ছবি ও দাম',
            'description' => 'এই পণ্যটির রং বেছে নিচের ফর্মে অর্ডার করুন। ক্যাশ অন ডেলিভারি, ৭ দিনের রিটার্ন।',
            'hero_image' => $pictures[0],
            'gallery' => $pictures,
            'features' => array_map(fn ($p) => ['title' => $p['title'], 'description' => $p['description']], $this->promises()),
        ];
    }

    /** Copy a bundled picture into public storage; returns its site-relative URL. */
    private function storePicture(string $source, string $baseName): string
    {
        if (! is_file($source)) {
            throw new RuntimeException("ডেমো ছবি পাওয়া যায়নি: {$source}");
        }

        $name = $baseName.'.png';
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
