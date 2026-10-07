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
            ->with(['variants', 'images', 'brand', 'reviews' => fn ($q) => $q->where('approved', true)->latest(), 'category'])
            ->where('slug', $slug)
            ->firstOrFail();

        // Related products: same category first, topped up with the newest
        // other products so the section is never empty on a small catalogue.
        $related = Product::storefront()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with('variants')
            ->withRating()
            ->latest()
            ->take(4)
            ->get();

        if ($related->count() < 4) {
            $related = $related->concat(
                Product::storefront()
                    ->where('id', '!=', $product->id)
                    ->whereNotIn('id', $related->pluck('id'))
                    ->with('variants')
                    ->withRating()
                    ->latest()
                    ->take(4 - $related->count())
                    ->get()
            );
        }

        return view('product.show', [
            'product' => $product,
            'similarProducts' => $related,
            'ratingCount' => $product->reviews->count(),
            'ratingAvg' => $product->reviews->count() ? round((float) $product->reviews->avg('rating'), 1) : null,
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
