<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\Product;

class HomeController extends Controller
{
    // Same two category slugs the old app's homepage sectioned off — see
    // src/app/page.tsx SECTION_CATEGORIES.
    private const SECTION_CATEGORIES = ['honey', 'mustard-oil'];

    public function __invoke()
    {
        $categories = Category::orderBy('name')->get();

        $heroBanners = Banner::where('position', 'hero')->where('active', true)->orderBy('sort_order')->get();
        $sideBanners = Banner::where('position', 'side')->where('active', true)->orderBy('sort_order')->get();
        $promoBanners = Banner::where('position', 'promo')->where('active', true)->orderBy('sort_order')->get();

        $discounted = Product::storefront()->onSale()->latest()->take(8)->get();

        $products = Product::storefront()->latest()->take(8)->get();

        $brands = Brand::has('products')->get();

        $blogs = Blog::whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->take(5)
            ->get();

        $activeFlashSale = FlashSale::with('items.product')
            ->where('is_active', true)
            ->where('end_time', '>', now())
            ->first();

        $sectionCategories = Category::whereIn('slug', self::SECTION_CATEGORIES)->get()->keyBy('slug');
        $sectionProducts = collect(self::SECTION_CATEGORIES)
            ->mapWithKeys(function ($slug) use ($sectionCategories) {
                if (! $sectionCategories->has($slug)) {
                    return [$slug => collect()];
                }

                return [$slug => Product::storefront()->where('category_id', $sectionCategories[$slug]->id)->take(8)->get()];
            });

        return view('home.index', compact(
            'categories', 'heroBanners', 'sideBanners', 'promoBanners',
            'discounted', 'products', 'brands', 'blogs', 'activeFlashSale',
            'sectionCategories', 'sectionProducts'
        ));
    }
}
