<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

/**
 * Storefront tracking switches and payload builders (GTM dataLayer + Meta
 * Pixel). Both are off unless an ID is saved in Admin → GTM / Pixel, and the
 * ENABLE_GTM kill switch (config services.gtm.enabled) turns both off for
 * dev/staging. Search-engine and other bots get no tracking at all: the
 * snippets are simply not printed for them. The JS side is resources/js/gtm.js.
 */
class Tracking
{
    /** The name of the "order placed" browser event unless the admin chose another one. */
    public const DEFAULT_ORDER_EVENT = 'OrderPlaced';

    /** Letters, digits and underscores, starting with a letter — what GTM and Meta accept as an event name. */
    public const EVENT_NAME_PATTERN = '/^[A-Za-z][A-Za-z0-9_]{0,39}$/';

    /** A Google Tag Manager container ID: GTM- and 4–10 letters/digits. */
    public const GTM_ID_PATTERN = '/^GTM-[A-Z0-9]{4,10}$/';

    /** Parts of a User-Agent that mean "not a person" (crawlers, link previewers, headless browsers, scripts). */
    private const BOT_PATTERN = '/bot\b|bot[\/;)]|crawl|spider|slurp|scrape|fetch|headless|phantom|puppeteer|playwright|selenium|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|facebookexternalhit|facebot|whatsapp|telegram|skype|preview|curl\/|wget|python-requests|python-urllib|aiohttp|httpx|go-http-client|java\/|okhttp|libwww|postman|axios|node-fetch|scrapy|httpclient|apache-httpclient|ahrefs|semrush|mj12|dotbot|petalbot|yandex|baidu|bytespider|ia_archiver|archive\.org/i';

    public static function isBot(?Request $request = null): bool
    {
        $request ??= request();

        return (bool) preg_match(self::BOT_PATTERN, (string) $request->userAgent());
    }

    public static function gtmId(): ?string
    {
        return self::isBot() ? null : Gtm::activeId();
    }

    /** Meta Pixel ID (digits only), or null when not configured / disabled / a bot is looking. */
    public static function pixelId(): ?string
    {
        if (config('services.gtm.enabled') === false || self::isBot()) {
            return null;
        }

        $id = preg_replace('/\D+/', '', (string) (SiteSetting::where('setting_key', 'meta_pixel_id')->value('setting_value') ?? ''));

        return $id !== '' ? $id : null;
    }

    public static function enabled(): bool
    {
        return self::gtmId() !== null || self::pixelId() !== null;
    }

    /** The admin's name for the "order placed" event (Admin → GTM / Pixel), OrderPlaced by default. */
    public static function orderEventName(): string
    {
        $name = trim((string) (SiteSetting::where('setting_key', 'tracking_order_event')->value('setting_value') ?? ''));

        return preg_match(self::EVENT_NAME_PATTERN, $name) ? $name : self::DEFAULT_ORDER_EVENT;
    }

    /** "Product (variant)" — how a chosen colour/size shows in the item name. */
    public static function itemName(Product $product, ?ProductVariant $variant = null): string
    {
        return $variant && filled($variant->label) ? "{$product->name} ({$variant->label})" : $product->name;
    }

    /** One GA4-style item. item_id stays the product id (it matches the product feed); the variant goes in item_variant. */
    public static function item(Product $product, float $price, int $quantity = 1, ?ProductVariant $variant = null): array
    {
        $item = [
            'item_id' => $product->id,
            'item_name' => self::itemName($product, $variant),
            'price' => $price,
            'quantity' => $quantity,
        ];

        if ($variant && filled($variant->label)) {
            $item['item_variant'] = $variant->label;
        }

        if ($category = $product->relationLoaded('category') ? $product->category?->name : $product->category()->value('name')) {
            $item['item_category'] = $category;
        }

        return $item;
    }

    /** GA4-style ecommerce payload for one product (with content_name / content_category for Meta). */
    public static function product(Product $product, float $price, int $quantity = 1, ?ProductVariant $variant = null): array
    {
        $item = self::item($product, $price, $quantity, $variant);

        return array_filter([
            'currency' => 'BDT',
            'value' => round($price * $quantity, 2),
            'content_name' => $product->name,
            'content_category' => $item['item_category'] ?? null,
            'items' => [$item],
        ], fn ($v) => $v !== null);
    }

    /** Fingerprint of a cart's contents: the same products, variants and quantities give the same value. */
    public static function cartSignature(array $lines): string
    {
        $rows = array_map(fn ($l) => [$l['product']->id, $l['variant']?->id, $l['quantity']], $lines);
        sort($rows);

        return md5((string) json_encode($rows));
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

    /**
     * The browser event sent the moment an order is placed (named in Admin → GTM / Pixel).
     * Same figures as Purchase, but its own event_id, so it never clashes with Purchase.
     *
     * @return array{name: string, event_id: string, ecommerce: array<string, mixed>}
     */
    public static function orderPlaced(Order $order): array
    {
        return [
            'name' => self::orderEventName(),
            'event_id' => 'placed-'.$order->invoice_no,
            'ecommerce' => self::purchase($order)['ecommerce'],
        ];
    }

    /**
     * What the browser knew when the order was placed — kept on the order so a server-side
     * Purchase (Meta Conversions API) can be sent later: the Pixel's _fbp / _fbc cookies, the
     * visitor's IP is already stored (ip_address), the browser, the page and the Google
     * Analytics client id. All are optional.
     *
     * @return array{fbp: ?string, fbc: ?string, client_user_agent: ?string, event_source_url: ?string, ga_client_id: ?string}
     */
    public static function requestContext(?Request $request = null): array
    {
        $request ??= request();
        $referer = (string) $request->headers->get('referer', '');
        // A Livewire call is /livewire/update; the page the visitor is on is the Referer.
        $page = $referer !== '' ? $referer : $request->fullUrl();

        $fbc = self::cookie($request, '_fbc');
        if (! $fbc && preg_match('/[?&]fbclid=([^&#]+)/', $page, $m)) {
            // The Pixel had not written _fbc yet: build it from the click id, the way Meta describes.
            $fbc = 'fb.1.'.(now()->timestamp * 1000).'.'.$m[1];
        }

        $ga = null;
        if (preg_match('/^GA\d+\.\d+\.(\d+\.\d+)$/', (string) self::cookie($request, '_ga'), $m)) {
            $ga = $m[1];
        }

        return [
            'fbp' => self::cookie($request, '_fbp'),
            'fbc' => $fbc,
            'client_user_agent' => self::clip($request->userAgent(), 512),
            'event_source_url' => self::clip($page, 2000),
            'ga_client_id' => $ga,
        ];
    }

    private static function cookie(Request $request, string $name): ?string
    {
        $value = $request->cookie($name);

        return is_string($value) && $value !== '' ? self::clip($value, 255) : null;
    }

    private static function clip(?string $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
