<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Response;

/**
 * Google Merchant / Meta (Facebook) compatible product feed — a faithful
 * port of the old Next.js app's src/app/api/feed/facebook/route.ts:
 * RSS 2.0 with the g: namespace, active products only, "BDT"-suffixed
 * prices, same Cache-Control header.
 *
 * Deliberate improvement over the old app: <g:brand> uses the product's
 * real brand relation (falling back to the site name) instead of the old
 * app's category-name-as-brand quirk. The old app used the category name
 * for g:brand, which doesn't match what "brand" means in the Merchant feed
 * spec — Phase 2 already gives every product a proper brand_id/Brand
 * relation, so using it here is strictly more correct and is trivial to do,
 * rather than faithfully reproducing what was likely a copy-paste bug.
 */
class FeedController extends Controller
{
    public function facebook(): Response
    {
        // Storefront-visible AND explicitly active — same intent as the old
        // app's `WHERE is_active = 1`, adapted to this app's is_active +
        // status + soft-delete model (Product::scopeStorefront already
        // encodes exactly that combination).
        $products = Product::storefront()->with(['category', 'brand'])->get();

        $baseUrl = rtrim(config('site.url'), '/');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">'."\n"
            .'  <channel>'."\n"
            .'    <title>'.$this->escape(\App\Support\SiteSettingsHelper::siteName().' Catalog').'</title>'."\n"
            .'    <link>'.$this->escape($baseUrl).'</link>'."\n"
            .'    <description>Dynamic product feed for Facebook and Google</description>'."\n";

        foreach ($products as $product) {
            $link = $baseUrl.'/product/'.$product->slug;
            $imageLink = $product->image
                ? \App\Support\Media::absolute($product->image)
                : $baseUrl.'/placeholder.png';

            $availability = $product->stock > 0 ? 'in stock' : 'out of stock';
            $price = number_format((float) $product->price, 2, '.', '').' BDT';
            $hasSale = $product->hasDiscount();
            $salePrice = $hasSale ? number_format((float) $product->sale_price, 2, '.', '').' BDT' : $price;

            $title = $this->escape($product->name);
            $description = $this->escape($product->description ?: $product->name);
            $brand = $this->escape($product->brand->name ?? \App\Support\SiteSettingsHelper::siteName());

            $xml .= "\n    <item>\n"
                ."      <g:id>{$product->id}</g:id>\n"
                ."      <g:title>{$title}</g:title>\n"
                ."      <g:description>{$description}</g:description>\n"
                ."      <g:link>{$this->escape($link)}</g:link>\n"
                ."      <g:image_link>{$this->escape($imageLink)}</g:image_link>\n"
                ."      <g:brand>{$brand}</g:brand>\n"
                ."      <g:condition>new</g:condition>\n"
                ."      <g:availability>{$availability}</g:availability>\n"
                ."      <g:price>{$price}</g:price>\n"
                .($hasSale ? "      <g:sale_price>{$salePrice}</g:sale_price>\n" : '')
                .'    </item>';
        }

        $xml .= "\n  </channel>\n</rss>";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 's-maxage=3600, stale-while-revalidate',
        ]);
    }

    protected function escape(string $value): string
    {
        return str_replace(
            ['&', '<', '>', "'", '"'],
            ['&amp;', '&lt;', '&gt;', '&apos;', '&quot;'],
            $value
        );
    }
}
