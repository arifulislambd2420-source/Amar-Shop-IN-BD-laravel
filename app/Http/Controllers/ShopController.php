<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Product;
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

    /** /shop and /offers: same filters, sorting and page layout. */
    private function listing(Request $request, $query, string $title, string $view = 'shop.index')
    {
        $products = $query
            ->filter($request->only(['category', 'brand', 'q', 'sort']))
            ->with('variants')->withRating() // read by every product card's AddToCart
            ->paginate(12)
            ->withQueryString();

        $categories = StorefrontCache::categories();
        $brands = Brand::orderBy('name')->get();

        return view($view, [
            'activeCategory' => $request->filled('category') ? $categories->firstWhere('slug', (string) $request->query('category')) : null,
            'activeBrand' => $request->filled('brand') ? $brands->firstWhere('id', (int) $request->query('brand')) : null,
            'search' => trim((string) $request->query('q', '')),
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'title' => $title,
        ]);
    }
}
