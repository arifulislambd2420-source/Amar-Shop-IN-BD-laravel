<?php

namespace App\Support;

use App\Models\Banner;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cache for data that is read on nearly every page but changes
 * rarely: the category list (menu, filters) and the home page banners.
 * Models that feed it use App\Models\Concerns\FlushesStorefrontCache, so
 * saving/deleting a category, banner, product, blog post or landing page in
 * the admin clears it immediately (site settings are cached by
 * SiteSettingsHelper and cleared by the Site Setting page on save).
 */
class StorefrontCache
{
    private const TTL = 600;

    public const KEYS = [
        'storefront.categories',
        'storefront.banners.hero',
        'storefront.banners.side',
        'storefront.banners.promo',
    ];

    // Only plain arrays are cached and the models are rebuilt on read: with
    // the database/file cache, config('cache.serializable_classes') is false,
    // so a cached Eloquent object would come back as __PHP_Incomplete_Class.

    /** @return Collection<int, Category> */
    public static function categories(): Collection
    {
        $rows = Cache::remember('storefront.categories', self::TTL, fn () => Category::orderBy('name')->get()->toArray());

        return Category::hydrate($rows);
    }

    /** @return Collection<int, Banner> */
    public static function banners(string $position): Collection
    {
        $rows = Cache::remember("storefront.banners.{$position}", self::TTL, fn () => Banner::where('position', $position)
            ->where('active', true)
            ->orderBy('sort_order')
            ->get()
            ->toArray());

        return Banner::hydrate($rows);
    }

    public static function flush(): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget($key);
        }

        Cache::forget('sitemap.xml');
        Cache::forget('nav.has_blog');
    }
}
