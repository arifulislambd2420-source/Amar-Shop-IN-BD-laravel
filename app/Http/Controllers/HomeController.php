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

        // ->with('variants'): every product card's AddToCart reads the
        // product's variants; eager-loading here avoids a query per card.
        $discounted = Product::storefront()->onSale()->with('variants')->latest()->take(8)->get();

        $products = Product::storefront()->with('variants')->latest()->take(8)->get();

        $brands = Brand::whereHas('products', fn ($q) => $q->storefront())->get();

        $blogs = Blog::whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->take(5)
            ->get();

        // Only storefront-visible products: a hidden/draft/inactive (or
        // soft-deleted) product in a flash sale used to still show here,
        // linking to a product page that 404s.
        $activeFlashSale = FlashSale::with(['items.product' => fn ($q) => $q->storefront()->with('variants')])
            ->where('is_active', true)
            ->where('end_time', '>', now())
            ->first();

        $activeFlashSale?->setRelation(
            'items',
            $activeFlashSale->items->filter(fn ($item) => $item->product !== null)->values(),
        );

        $sectionCategories = Category::whereIn('slug', self::SECTION_CATEGORIES)->get()->keyBy('slug');
        $sectionProducts = collect(self::SECTION_CATEGORIES)
            ->mapWithKeys(function ($slug) use ($sectionCategories) {
                if (! $sectionCategories->has($slug)) {
                    return [$slug => collect()];
                }

                return [$slug => Product::storefront()->with('variants')->where('category_id', $sectionCategories[$slug]->id)->take(8)->get()];
            });

        return view('home.index', compact(
            'categories', 'heroBanners', 'sideBanners', 'promoBanners',
            'discounted', 'products', 'brands', 'blogs', 'activeFlashSale',
            'sectionCategories', 'sectionProducts'
        ));
    }
}
