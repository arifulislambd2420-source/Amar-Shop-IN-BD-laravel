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
            ->paginate(12)
            ->withQueryString();

        $categories = Category::orderBy('name')->get();
        $brands = Brand::orderBy('name')->get();

        return view('shop.index', [
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
            ->paginate(12)
            ->withQueryString();

        $categories = Category::orderBy('name')->get();
        $brands = Brand::orderBy('name')->get();

        return view('shop.offers', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'title' => 'অফার সমূহ',
        ]);
    }
}
