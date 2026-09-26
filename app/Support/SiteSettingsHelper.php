<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Thin cached reader for the site_settings key/value table (logo, favicon,
 * etc — managed from the Filament "Site Setting" page). Falls back to null
 * so callers can layer their own default (e.g. text logo when no image is
 * set).
 */
class SiteSettingsHelper
{
    public static function get(string $key, ?string $default = null): ?string
    {
        return Cache::remember("site_setting:{$key}", 300, function () use ($key, $default) {
            return SiteSetting::where('setting_key', $key)->value('setting_value') ?? $default;
        });
    }
}
