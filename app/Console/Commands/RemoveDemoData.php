<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\LandingPage;
use App\Models\MediaLibrary;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Media;
use App\Support\StorefrontCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the sample data added by DemoClothingSeeder and DemoLandingSeeder — and only that:
 *  - products and categories marked is_demo (variants, reviews, flash-sale
 *    rows go with their product),
 *  - demo landing pages (is_demo),
 *  - the placeholder pictures stored as media/demo-*.png.
 * Real products, real categories and uploads (uuid-named files) are never touched.
 *
 * A demo product that a customer actually ordered is kept (hidden from the
 * shop instead of deleted) so order history stays intact; the same goes for
 * a demo category that still holds a real product. A demo landing page that
 * received an order is switched off instead of deleted, for the same reason.
 */
class RemoveDemoData extends Command
{
    protected $signature = 'demo:remove
        {--dry-run : Only show what would be deleted}
        {--force : Do not ask for confirmation}';

    protected $description = 'Delete the demo (sample) products, categories and pictures added by DemoClothingSeeder';

    /** Placeholder pictures are the only media files that start with "demo-". */
    private const PICTURE = '#^/storage/media/demo-[a-z0-9-]+\.png$#';

    public function handle(): int
    {
        if (! Schema::hasColumn('products', 'is_demo') || ! Schema::hasColumn('categories', 'is_demo')) {
            $this->warn('ডেমো কলাম নেই (migrate হয়নি) — মোছার মতো ডেমো ডাটা নেই।');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');

        $hasLanding = Schema::hasColumn('landing_pages', 'is_demo');
        $pages = $hasLanding ? LandingPage::where('is_demo', true)->get() : collect();
        $orderedPages = Order::whereIn('landing_page_id', $pages->pluck('id'))->distinct()->pluck('landing_page_id')->all();
        $deletablePages = $pages->reject(fn ($p) => in_array($p->id, $orderedPages, true));
        $keptPages = $pages->filter(fn ($p) => in_array($p->id, $orderedPages, true));

        $products = Product::withTrashed()->where('is_demo', true)->get();
        $ordered = OrderItem::whereIn('product_id', $products->pluck('id'))->distinct()->pluck('product_id')->all();
        $deletable = $products->reject(fn ($p) => in_array($p->id, $ordered, true));
        $kept = $products->filter(fn ($p) => in_array($p->id, $ordered, true));
        $categories = Category::where('is_demo', true)->get();
        $pictures = MediaLibrary::where('file_path', 'like', '/storage/media/demo-%')->get()
            ->filter(fn ($m) => preg_match(self::PICTURE, $m->file_path));

        if ($products->isEmpty() && $categories->isEmpty() && $pictures->isEmpty() && $pages->isEmpty()) {
            $this->info('কোনো ডেমো ডাটা পাওয়া যায়নি। কিছু করার নেই।');

            return self::SUCCESS;
        }

        $this->line('মোছা হবে: '.$deletable->count().' টি প্রোডাক্ট (রং/সাইজ-সহ), '.$deletablePages->count().' টি ল্যান্ডিং পেজ, ডেমো ক্যাটাগরি (যেগুলোতে আর প্রোডাক্ট থাকবে না), ও ডেমো ছবি।');

        if ($kept->isNotEmpty()) {
            $this->warn($kept->count().' টি ডেমো প্রোডাক্ট কোনো অর্ডারে আছে — মোছা হবে না, শুধু শপ থেকে লুকানো হবে: '.$kept->pluck('name')->implode(', '));
        }

        if ($keptPages->isNotEmpty()) {
            $this->warn($keptPages->count().' টি ডেমো ল্যান্ডিং পেজে অর্ডার এসেছে — মোছা হবে না, শুধু বন্ধ করা হবে: '.$keptPages->pluck('slug')->implode(', '));
        }

        if ($dry) {
            $this->info('[dry run] কিছু মোছা হয়নি।');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('এগুলো মুছে ফেলবেন? (আসল ডাটা মুছবে না)')) {
            $this->line('বাতিল করা হয়েছে।');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($deletable, $kept, $deletablePages, $keptPages) {
            foreach ($deletablePages as $page) {
                $page->delete();
            }

            foreach ($keptPages as $page) {
                $page->forceFill(['is_active' => false])->save();
            }

            foreach ($deletable as $product) {
                // Explicit deletes first so it also works where FK cascades are off.
                $product->variants()->delete();
                $product->images()->delete();
                $product->reviews()->delete();
                DB::table('flash_sale_items')->where('product_id', $product->id)->delete();
                $product->forceDelete();
            }

            foreach ($kept as $product) {
                $product->forceFill(['is_active' => false, 'status' => 'hidden'])->save();
            }

            // Demo categories go only when nothing (real or demo, even trashed) is left in them.
            Category::where('is_demo', true)->get()->each(function (Category $category) {
                if (! Product::withTrashed()->where('category_id', $category->id)->exists()) {
                    $category->delete();
                }
            });
        });

        $removed = 0;

        foreach ($pictures as $picture) {
            // A picture still used by a kept product must stay.
            if (Product::withTrashed()->where('image', $picture->file_path)->exists()
                || ProductVariant::where('image', $picture->file_path)->exists()
                || ($hasLanding && LandingPage::where('is_demo', true)->where(fn ($q) => $q->where('hero_image', $picture->file_path)->orWhere('blocks', 'like', '%'.substr(json_encode($picture->file_path), 1, -1).'%')->orWhere('gallery', 'like', '%'.substr(json_encode($picture->file_path), 1, -1).'%'))->exists())) {
                continue;
            }

            $diskPath = ltrim(substr($picture->file_path, strlen('/storage/')), '/');
            Storage::disk(config('media.disk'))->delete($diskPath);
            Media::unmirror($diskPath);
            $picture->delete();
            $removed++;
        }

        StorefrontCache::flush();

        $this->info("ডেমো ডাটা মুছে ফেলা হয়েছে ({$deletable->count()} টি প্রোডাক্ট, {$removed} টি ছবি)।");

        return self::SUCCESS;
    }
}
