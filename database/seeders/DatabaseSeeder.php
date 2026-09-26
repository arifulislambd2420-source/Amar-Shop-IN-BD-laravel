<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Replicates the old Next.js `seedIfEmpty` (src/lib/db.ts):
 * 4 brands, 4 categories, 6 sample products, 4 variants, 1 admin user.
 * Idempotent: only seeds a section when it is empty (so it is safe to re-run
 * without migrate:fresh).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Brands ------------------------------------------------------
        $brandIds = [];
        if (Brand::count() === 0) {
            foreach (['Sundarban', 'Home Made', 'Organic BD', 'Deshi'] as $name) {
                $brandIds[$name] = Brand::create(['name' => $name, 'logo' => null])->id;
            }
        } else {
            $brandIds = Brand::pluck('id', 'name')->all();
        }

        // ---- Categories + Products + Variants -----------------------------
        if (Product::count() === 0) {
            $cats = [
                'honey' => 'মধু (Honey)',
                'mustard-oil' => 'সরিষার তেল (Mustard Oil)',
                'ghee' => 'ঘি (Ghee)',
                'dates' => 'খেজুর (Dates)',
            ];
            $catIds = [];
            foreach ($cats as $slug => $name) {
                $catIds[$slug] = Category::create(['name' => $name, 'slug' => $slug])->id;
            }

            $products = [
                ['name' => 'সুন্দরবন মধু ১kg', 'slug' => 'sundarban-honey-1kg', 'description' => 'খাঁটি সুন্দরবন মধু, ১ কেজি বোতল।', 'price' => 2500, 'sale_price' => null, 'image' => '/products/honey1.svg', 'category_id' => $catIds['honey'], 'stock' => 20, 'brand_id' => $brandIds['Sundarban']],
                ['name' => 'লিচু ফুলের মধু ৫০০g', 'slug' => 'lychee-honey-500g', 'description' => 'খাঁটি লিচু ফুলের মধু।', 'price' => 600, 'sale_price' => 550, 'image' => '/products/honey2.svg', 'category_id' => $catIds['honey'], 'stock' => 35, 'brand_id' => $brandIds['Sundarban']],
                ['name' => 'দেশি সরিষার তেল ১ লিটার', 'slug' => 'deshi-mustard-oil-1l', 'description' => 'কাঠের ঘানিতে ভাঙানো খাঁটি সরিষার তেল।', 'price' => 340, 'sale_price' => null, 'image' => '/products/mustard1.svg', 'category_id' => $catIds['mustard-oil'], 'stock' => 50, 'brand_id' => $brandIds['Deshi']],
                ['name' => 'দেশি সরিষার তেল ৫ লিটার', 'slug' => 'deshi-mustard-oil-5l', 'description' => 'কাঠের ঘানিতে ভাঙানো খাঁটি সরিষার তেল, বড় বোতল।', 'price' => 1700, 'sale_price' => null, 'image' => '/products/mustard2.svg', 'category_id' => $catIds['mustard-oil'], 'stock' => 15, 'brand_id' => $brandIds['Deshi']],
                ['name' => 'খাঁটি গাওয়া ঘি ৫০০g', 'slug' => 'pure-ghee-500g', 'description' => 'খাঁটি দুধের গাওয়া ঘি।', 'price' => 900, 'sale_price' => null, 'image' => '/products/ghee1.svg', 'category_id' => $catIds['ghee'], 'stock' => 25, 'brand_id' => $brandIds['Home Made']],
                ['name' => 'সুক্কারি মরিয়ম খেজুর ১kg', 'slug' => 'sukkari-dates-1kg', 'description' => 'প্রিমিয়াম মানের সুক্কারি খেজুর।', 'price' => 1500, 'sale_price' => 1400, 'image' => '/products/dates1.svg', 'category_id' => $catIds['dates'], 'stock' => 18, 'brand_id' => $brandIds['Organic BD']],
            ];

            $productIds = [];
            foreach ($products as $p) {
                $productIds[$p['slug']] = Product::create($p)->id;
            }

            if (ProductVariant::count() === 0) {
                $variants = [
                    ['product_slug' => 'sundarban-honey-1kg', 'label' => '৫০০g', 'price' => 1300, 'stock' => 20],
                    ['product_slug' => 'sundarban-honey-1kg', 'label' => '১kg', 'price' => 2500, 'stock' => 20],
                    ['product_slug' => 'pure-ghee-500g', 'label' => '৫০০g', 'price' => 900, 'stock' => 25],
                    ['product_slug' => 'pure-ghee-500g', 'label' => '১kg', 'price' => 1750, 'stock' => 12],
                ];
                foreach ($variants as $v) {
                    ProductVariant::create([
                        'product_id' => $productIds[$v['product_slug']],
                        'label' => $v['label'],
                        'price' => $v['price'],
                        'stock' => $v['stock'],
                    ]);
                }
            }
        }

        // ---- Admin user ---------------------------------------------------
        if (AdminUser::count() === 0) {
            AdminUser::create([
                'username' => 'admin',
                'password' => Hash::make('admin123'),
                'role' => 'super_admin',
            ]);
        }
    }
}
