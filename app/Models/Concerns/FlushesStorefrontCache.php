<?php

namespace App\Models\Concerns;

use App\Support\StorefrontCache;

/**
 * Clears the storefront caches (categories, banners, sitemap, blog menu flag)
 * whenever the model changes. A product's stock changing on every order does
 * not count — only real catalogue edits do.
 */
trait FlushesStorefrontCache
{
    public static function bootFlushesStorefrontCache(): void
    {
        $flush = fn () => StorefrontCache::flush();

        static::created($flush);
        static::deleted($flush);

        static::updated(function ($model): void {
            $changed = array_keys($model->getChanges());

            if ($changed !== [] && array_diff($changed, ['stock', 'updated_at']) === []) {
                return;
            }

            StorefrontCache::flush();
        });
    }
}
