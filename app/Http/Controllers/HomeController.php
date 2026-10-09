<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\Product;
use App\Support\HomeSections;
use App\Support\StorefrontCache;
use Illuminate\Support\Collection;

/**
 * Home page. Which sections show, and in what order, is the shop owner's
 * choice (Site Setting → হোমপেজের সেকশন, see HomeSections); data is only
 * loaded for the sections that are switched on.
 */
class HomeController extends Controller
{
    public function __invoke()
    {
        $sections = HomeSections::enabled();
        $on = fn (string $key): bool => in_array($key, $sections, true);

        // ->with('variants')->withRating(): every product card's AddToCart reads the
        // product's variants; eager-loading here avoids a query per card.
        $cards = fn () => Product::storefront()->with('variants')->withRating();

        $data = [
            'sections' => $sections,
            'categories' => $on('categories') ? StorefrontCache::categories() : collect(),
            'heroBanners' => $on('hero') ? StorefrontCache::banners('hero') : collect(),
            'sideBanners' => $on('hero') ? StorefrontCache::banners('side') : collect(),
            'promoBanners' => $on('promo') ? StorefrontCache::banners('promo') : collect(),
            'discounted' => $on('offers') ? $cards()->onSale()->latest()->take(8)->get() : collect(),
            'products' => $on('new_products') ? $cards()->latest()->take(8)->get() : collect(),
            'brands' => $on('brands') ? Brand::whereHas('products', fn ($q) => $q->storefront())->get() : collect(),
            'blogs' => $on('blog')
                ? Blog::whereNotNull('published_at')->where('published_at', '<=', now())->latest('published_at')->take(5)->get()
                : collect(),
            'activeFlashSale' => $on('flash_sale') ? $this->activeFlashSale() : null,
            'showcase' => $on('showcase') ? $this->showcase($cards) : collect(),
        ];

        return view('home.index', $data);
    }

    /** The running flash sale with only its storefront-visible products (a hidden one would link to a 404). */
    private function activeFlashSale(): ?FlashSale
    {
        $sale = FlashSale::with(['items.product' => fn ($q) => $q->storefront()->with('variants')->withRating()])
            ->where('is_active', true)
            ->where('end_time', '>', now())
            ->first();

        $sale?->setRelation('items', $sale->items->filter(fn ($item) => $item->product !== null)->values());

        return $sale;
    }

    /** @return Collection<int, array{category: Category, products: Collection}> */
    private function showcase(callable $cards): Collection
    {
        return HomeSections::showcaseCategories()->map(fn ($category) => [
            'category' => $category,
            'products' => $cards()->where('category_id', $category->id)->latest()->take(8)->get(),
        ]);
    }
}
