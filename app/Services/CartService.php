<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Session;

/**
 * Session-backed cart (mirrors the old Next.js app's client-side Zustand
 * cart store, but kept server-side here since this isn't a SPA).
 *
 * Session shape: [ "productId:variantId|0" => ["product_id", "variant_id", "quantity"] ]
 */
class CartService
{
    protected const SESSION_KEY = 'cart';

    /**
     * lines() memoized for the rest of this request (the service is bound
     * scoped in AppServiceProvider): the cart page, drawer, badge and
     * checkout each call lines()/subtotal(), which used to re-query the same
     * products up to 7 times per request. Every mutator below resets it.
     */
    protected ?array $lines = null;

    protected function key(int $productId, ?int $variantId): string
    {
        return $productId.':'.($variantId ?? 0);
    }

    public function all(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    public function add(int $productId, int $quantity = 1, ?int $variantId = null): void
    {
        $cart = $this->all();
        $key = $this->key($productId, $variantId);

        if (isset($cart[$key])) {
            $cart[$key]['quantity'] += $quantity;
        } else {
            $cart[$key] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
            ];
        }

        Session::put(self::SESSION_KEY, $cart);
        $this->lines = null;
    }

    public function updateQuantity(int $productId, ?int $variantId, int $quantity): void
    {
        $cart = $this->all();
        $key = $this->key($productId, $variantId);

        if (! isset($cart[$key])) {
            return;
        }

        if ($quantity <= 0) {
            unset($cart[$key]);
        } else {
            // Never more than is in stock.
            $line = collect($this->lines())->firstWhere('key', $key);
            $cart[$key]['quantity'] = $line ? min($quantity, max(1, (int) $line['stock'])) : $quantity;
        }

        Session::put(self::SESSION_KEY, $cart);
        $this->lines = null;
    }

    public function remove(int $productId, ?int $variantId = null): void
    {
        $cart = $this->all();
        unset($cart[$this->key($productId, $variantId)]);
        Session::put(self::SESSION_KEY, $cart);
        $this->lines = null;
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
        $this->lines = null;
    }

    public function count(): int
    {
        return array_sum(array_column($this->all(), 'quantity'));
    }

    /**
     * Hydrate cart rows with live product/variant data (name, image, current
     * price, stock) so the cart always reflects up-to-date pricing/stock,
     * dropping any line whose product no longer exists.
     */
    public function lines(): array
    {
        return $this->lines ??= $this->buildLines();
    }

    protected function buildLines(): array
    {
        $cart = $this->all();
        if (empty($cart)) {
            return [];
        }

        $productIds = array_unique(array_column($cart, 'product_id'));
        // storefront(): a product hidden/drafted/archived by an admin after
        // a customer carted it drops out of the cart, instead of staying
        // purchasable at checkout.
        $products = Product::storefront()->whereIn('id', $productIds)->with('variants')->get()->keyBy('id');

        $lines = [];
        foreach ($cart as $key => $row) {
            $product = $products->get($row['product_id']);
            if (! $product) {
                continue;
            }

            $variant = null;
            if (! empty($row['variant_id'])) {
                $variant = $product->variants->firstWhere('id', $row['variant_id']);
                if (! $variant) {
                    continue;
                }
            }

            $price = $variant ? (float) $variant->price : (float) ($product->sale_price ?? $product->price);
            $stock = $variant ? $variant->stock : $product->stock;
            $quantity = min($row['quantity'], max($stock, 0));

            $lines[] = [
                'key' => $key,
                'product' => $product,
                'variant' => $variant,
                'name' => $product->name.($variant ? " ({$variant->label})" : ''),
                'image' => $product->image,
                'price' => $price,
                'quantity' => $quantity,
                'stock' => $stock,
                'line_total' => $price * $quantity,
            ];
        }

        return $lines;
    }

    public function subtotal(): float
    {
        return array_sum(array_column($this->lines(), 'line_total'));
    }
}
