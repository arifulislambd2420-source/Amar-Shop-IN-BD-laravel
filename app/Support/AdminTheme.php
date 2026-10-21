<?php

namespace App\Support;

/**
 * The admin panel's own colours — separate from the shop's brand colours (Site settings → Colours):
 *  - the sidebar colour (dark navy by default; light text and icons on it, or dark ones if a light
 *    colour is chosen), and
 *  - the main colour (active menu, buttons, links; a bright blue by default).
 * Both are set in Site settings → General → Admin colours.
 */
class AdminTheme
{
    public const DEFAULT_PRIMARY = '#2f6df6';

    public const DEFAULT_SIDEBAR = '#0b1437';

    public const KEYS = ['admin_color_primary', 'admin_color_sidebar'];

    public static function defaults(): array
    {
        return ['admin_color_primary' => self::DEFAULT_PRIMARY, 'admin_color_sidebar' => self::DEFAULT_SIDEBAR];
    }

    private static function color(string $key): string
    {
        $value = SiteSettingsHelper::get($key);

        return $value && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : self::defaults()[$key];
    }

    public static function primary(): string
    {
        return self::color('admin_color_primary');
    }

    public static function sidebar(): string
    {
        return self::color('admin_color_sidebar');
    }

    /** Perceived brightness 0 (black) … 1 (white). */
    public static function brightness(string $hex): float
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    }

    public static function sidebarIsDark(?string $hex = null): bool
    {
        return self::brightness($hex ?? self::sidebar()) < 0.55;
    }

    /**
     * CSS variables for the sidebar: its background and the text / icon colours that stay readable on it.
     *
     * @return array<string, string>
     */
    public static function sidebarVars(): array
    {
        $dark = self::sidebarIsDark();

        return [
            '--sb-bg' => self::sidebar(),
            '--sb-text' => $dark ? '#f1f5f9' : '#0f172a',
            '--sb-muted' => $dark ? 'rgba(241, 245, 249, .62)' : 'rgba(15, 23, 42, .62)',
            '--sb-hover' => $dark ? 'rgba(255, 255, 255, .08)' : 'rgba(15, 23, 42, .07)',
            '--sb-line' => $dark ? 'rgba(255, 255, 255, .12)' : 'rgba(15, 23, 42, .12)',
        ];
    }
}
