<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::storefront()
            ->filter($request->only(['category', 'brand', 'q', 'sort']))
            ->with('variants')->withRating() // read by every product card's AddToCart
            ->paginate(12)
            ->withQueryString();

        $categories = \App\Support\StorefrontCache::categories();
        $brands = Brand::orderBy('name')->get();

        return view('shop.index', [
            'activeBrand' => $request->filled('brand') ? $brands->firstWhere('id', (int) $request->query('brand')) : null,
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'title' => 'শপ',
        ]);
    }

    public function offers(Request $request)
    {
        $products = Product::storefront()
            ->onSale()
            ->filter($request->only(['category', 'brand', 'q', 'sort']))
            ->with('variants')->withRating() // read by every product card's AddToCart
            ->paginate(12)
            ->withQueryString();

        $categories = \App\Support\StorefrontCache::categories();
        $brands = Brand::orderBy('name')->get();

        return view('shop.offers', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'title' => 'অফার সমূহ',
        ]);
    }
}
