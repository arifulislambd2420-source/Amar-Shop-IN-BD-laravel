<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Thin cached reader for the site_settings key/value table (logo, favicon,
 * contact details, social links — managed from the Filament "Site Setting"
 * page). Falls back to null so callers can layer their own default (e.g.
 * text logo when no image is set).
 */
class SiteSettingsHelper
{
    public static function get(string $key, ?string $default = null): ?string
    {
        // Cache the raw value ('' when unset) so a missing key is cached too
        // and the default is never baked into the cache.
        $value = Cache::remember("site_setting:{$key}", 300, function () use ($key) {
            return (string) (SiteSetting::where('setting_key', $key)->value('setting_value') ?? '');
        });

        return $value !== '' ? $value : $default;
    }

    public static function forget(string $key): void
    {
        Cache::forget("site_setting:{$key}");
    }

    // Contact / social details: the Site Setting value wins, config/site.php
    // (env) is the fallback when the setting is empty.

    /** Theme color name => CSS variable it drives (see resources/css/app.css). */
    public const COLOR_VARS = [
        'brand' => '--brand',
        'secondary' => '--secondary',
        'accent' => '--accent',
        'background' => '--surface',
        'text' => '--ink',
        'success' => '--success',
        'error' => '--error',
    ];

    /** @return array<string, string> */
    public static function colorDefaults(): array
    {
        return config('site.colors');
    }

    /** A theme color: the Site Setting value when it is a valid #hex, else the default. */
    public static function color(string $name): string
    {
        $value = self::get("color_{$name}");

        return $value && preg_match('/^#[0-9a-fA-F]{6}$/', $value)
            ? strtolower($value)
            : self::colorDefaults()[$name];
    }

    /** `:root{…}` with only the colors that differ from the defaults ('' when none). */
    public static function themeCss(): string
    {
        $css = '';

        foreach (self::COLOR_VARS as $name => $var) {
            $color = self::color($name);

            if ($color !== strtolower(self::colorDefaults()[$name])) {
                $css .= "{$var}:{$color};";
            }
        }

        return $css === '' ? '' : ":root{{$css}}";
    }

    /** Site name (Bangla); config/site.php when the setting is empty. */
    public static function siteName(): string
    {
        return self::get('site_name') ?: config('site.name');
    }

    /** True when an admin has set a custom Bangla site name. */
    public static function hasCustomSiteName(): bool
    {
        return (bool) self::get('site_name');
    }

    /** Site name (English / legal); config/site.php when the setting is empty. */
    public static function siteNameEn(): string
    {
        return self::get('site_name_en') ?: config('site.legal_name');
    }

    public static function phone(): ?string
    {
        return self::get('contact_phone') ?: config('site.support_phone');
    }

    /** Value for tel: links — digits with an optional leading +. */
    public static function phoneRaw(): ?string
    {
        $phone = self::get('contact_phone');

        if (! $phone) {
            return config('site.support_phone_raw');
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return $digits === '' ? null : (str_starts_with(trim($phone), '+') ? '+' : '').$digits;
    }

    /** Digits only, as wa.me expects (country code, no +). */
    public static function whatsapp(): ?string
    {
        $number = self::get('contact_whatsapp');

        return $number ? preg_replace('/\D+/', '', $number) : config('site.whatsapp_number');
    }

    public static function email(): ?string
    {
        return self::get('contact_email') ?: config('site.support_email');
    }

    public static function address(): ?string
    {
        return self::get('contact_address') ?: config('site.address');
    }

    /** @param  'facebook'|'youtube'|'instagram'|'tiktok'  $network */
    public static function social(string $network): ?string
    {
        return self::get("social_{$network}") ?: (config("site.social.{$network}") ?: null);
    }
}
