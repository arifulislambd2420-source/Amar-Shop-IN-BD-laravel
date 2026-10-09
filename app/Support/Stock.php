<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * The one place stock moves for orders.
 *
 * A product with sizes/variants keeps its own `stock` equal to the sum of its
 * variants' stock (also kept in step when an admin edits a variant — see
 * ProductVariant), so lists, low-stock alerts and the storefront agree.
 * A product without variants just uses its own `stock`.
 *
 * Callers run these inside their DB transaction, after locking the rows.
 */
class Stock
{
    /** Take sold units out of stock. */
    public static function take(int $productId, ?int $variantId, int $quantity): void
    {
        self::move($productId, $variantId, -$quantity);
    }

    /** Give units back (cancelled order). */
    public static function release(int $productId, ?int $variantId, int $quantity): void
    {
        self::move($productId, $variantId, $quantity);
    }

    /** Set a product's stock to the sum of its variants (no-op without variants). */
    public static function syncProduct(int $productId): void
    {
        $variants = ProductVariant::where('product_id', $productId);

        if ($variants->exists()) {
            Product::whereKey($productId)->update(['stock' => (int) $variants->sum('stock')]);
        }
    }

    private static function move(int $productId, ?int $variantId, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        $variant = $variantId ? ProductVariant::whereKey($variantId)->where('product_id', $productId)->first() : null;

        if ($variant) {
            ProductVariant::whereKey($variant->id)->increment('stock', $delta);
            self::syncProduct($productId);

            return;
        }

        Product::whereKey($productId)->increment('stock', $delta);
    }
}
