<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

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
        ], [
            'customer_name.required' => 'আপনার নাম লিখুন।',
            'customer_name.max' => 'নাম অনেক বড় হয়ে গেছে।',
            'rating.*' => '১ থেকে ৫ এর মধ্যে রেটিং দিন।',
            'comment.max' => 'মন্তব্য ২০০০ অক্ষরের মধ্যে লিখুন।',
        ]);

        Review::create([
            'product_id' => $product->id,
            'customer_name' => $data['customer_name'],
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? '',
            'approved' => false,
        ]);

        return redirect()->to(route('product.show', $product->slug).'#reviews')
            ->with('status', 'আপনার রিভিউ সাবমিট হয়েছে। এটি অ্যাডমিন অ্যাপ্রুভ করার পর দেখা যাবে।');
    }
}
