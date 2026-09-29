<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function show(string $slug)
    {
        $product = Product::storefront()
            ->with(['variants', 'reviews' => fn ($q) => $q->where('approved', true)->latest(), 'category'])
            ->where('slug', $slug)
            ->firstOrFail();

        $similarProducts = Product::storefront()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with('variants')
            ->take(4)
            ->get();

        return view('product.show', [
            'product' => $product,
            'similarProducts' => $similarProducts,
        ]);
    }

    /**
     * New reviews are unapproved by default and only show up on the product
     * page once approved from the admin (Filament Reviews resource).
     */
    public function storeReview(Request $request, string $slug)
    {
        $product = Product::storefront()->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        Review::create([
            'product_id' => $product->id,
            'customer_name' => $data['customer_name'],
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? '',
            'approved' => false,
        ]);

        return redirect()->route('product.show', $product->slug)
            ->with('status', 'আপনার রিভিউ সাবমিট হয়েছে। এটি অ্যাডমিন অ্যাপ্রুভ করার পর দেখা যাবে।');
    }
}
