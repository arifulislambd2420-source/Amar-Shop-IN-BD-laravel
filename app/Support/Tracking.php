<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use App\Models\SiteSetting;

/**
 * Storefront tracking switches and payload builders (GTM dataLayer + Meta
 * Pixel). Both are off unless an ID is saved in Admin → GTM / Pixel, and the
 * ENABLE_GTM kill switch (config services.gtm.enabled) turns both off for
 * dev/staging. The JS side is resources/js/gtm.js (trackEvent).
 */
class Tracking
{
    public static function gtmId(): ?string
    {
        return Gtm::activeId();
    }

    /** Meta Pixel ID (digits only), or null when not configured / disabled. */
    public static function pixelId(): ?string
    {
        if (config('services.gtm.enabled') === false) {
            return null;
        }

        $id = preg_replace('/\D+/', '', (string) (SiteSetting::where('setting_key', 'meta_pixel_id')->value('setting_value') ?? ''));

        return $id !== '' ? $id : null;
    }

    public static function enabled(): bool
    {
        return self::gtmId() !== null || self::pixelId() !== null;
    }

    /** GA4-style ecommerce payload for one product. */
    public static function product(Product $product, float $price, int $quantity = 1): array
    {
        return [
            'currency' => 'BDT',
            'value' => round($price * $quantity, 2),
            'items' => [[
                'item_id' => $product->id,
                'item_name' => $product->name,
                'price' => $price,
                'quantity' => $quantity,
            ]],
        ];
    }

    /**
     * Purchase payload: total, BDT, the order's invoice number as
     * transaction_id, plus the stable event_id Meta uses to de-duplicate.
     * Built from the stored order, never from client input.
     *
     * @return array{event_id: string, ecommerce: array<string, mixed>}
     */
    public static function purchase(Order $order): array
    {
        $order->loadMissing('items');

        return [
            'event_id' => 'purchase-'.$order->invoice_no,
            'ecommerce' => [
                'transaction_id' => $order->invoice_no,
                'value' => (float) $order->total,
                'currency' => 'BDT',
                'shipping' => (float) $order->shipping_fee,
                'items' => $order->items->map(fn ($item) => [
                    'item_id' => $item->product_id,
                    'item_name' => $item->product_name,
                    'price' => (float) $item->unit_price,
                    'quantity' => $item->quantity,
                ])->all(),
            ],
        ];
    }
}
