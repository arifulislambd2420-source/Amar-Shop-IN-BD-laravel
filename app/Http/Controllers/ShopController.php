<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Product;
use App\Support\ShopFilters;
use App\Support\StorefrontCache;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        return $this->listing($request, Product::storefront(), 'শপ');
    }

    public function offers(Request $request)
    {
        return $this->listing($request, Product::storefront()->onSale(), 'অফার সমূহ', 'shop.offers');
    }

    /** /shop and /offers: same filters (see ShopFilters), sorting and page layout. */
    private function listing(Request $request, $listing, string $title, string $view = 'shop.index')
    {
        $filters = ShopFilters::fromRequest($request);
        // ?q[]= or ?sort[]= (not text) is read as empty.
        $search = is_scalar($request->query('q')) ? trim((string) $request->query('q')) : '';
        $sortKey = is_scalar($request->query('sort')) ? (string) $request->query('sort') : '';

        // The choices (colours, sizes, price range) come from the products of this listing, before filtering.
        $options = ShopFilters::options($listing);

        $products = ShopFilters::apply(clone $listing, $filters)
            ->filter(['q' => $search, 'sort' => $sortKey])
            ->with('variants')->withRating() // read by every product card's AddToCart
            ->paginate(12)
            ->withQueryString();

        $categories = StorefrontCache::categories();
        $brands = Brand::orderBy('name')->get();

        return view($view, [
            'filters' => $filters,
            'filterCount' => ShopFilters::count($filters),
            'options' => $options,
            // Exactly one category / brand picked: the page is about it (heading, brand logo).
            'activeCategory' => count($filters['category']) === 1 ? $categories->firstWhere('slug', $filters['category'][0]) : null,
            'activeBrand' => count($filters['brand']) === 1 ? $brands->firstWhere('id', (int) $filters['brand'][0]) : null,
            'search' => $search,
            'sortKey' => $sortKey,
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'title' => $title,
        ]);
    }
}
