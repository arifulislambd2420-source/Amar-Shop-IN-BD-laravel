<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Where the admin panel lives: /admin unless an admin picked another path
 * (Admin → Admin address). Kept in a small file in storage/app, not the
 * database, because the panel's routes are registered while the app boots —
 * before the database can be asked anything — and so that the server command
 * `php artisan admin:reset-path` can put /admin back even if the database is
 * down. `git pull` never touches storage/, so deploys keep the choice.
 */
class AdminPath
{
    public const DEFAULT = 'admin';

    private const MIN = 3;

    private const MAX = 40;

    /** Words the site or the server already uses as a first path segment. */
    private const RESERVED = [
        'storage', 'livewire', 'build', 'vendor', 'filament', 'api', 'up', 'index', 'public',
        'login', 'logout', 'register', 'home', 'dashboard', 'assets', 'css', 'js', 'fonts', 'images', 'img',
        'favicon', 'robots', 'sitemap', 'sanctum', 'horizon', 'telescope', 'nova',
    ];

    private static ?string $fileOverride = null;

    private static ?string $cached = null;

    /** Tests point this at a temp file so the real choice is never touched. */
    public static function useFile(?string $file): void
    {
        self::$fileOverride = $file;
        self::$cached = null;
    }

    public static function file(): string
    {
        return self::$fileOverride ?? storage_path('app/admin-path.txt');
    }

    /** The path segment in use now (no slashes). Anything unreadable or invalid means "admin". */
    public static function current(): string
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $value = is_file(self::file()) ? trim((string) @file_get_contents(self::file())) : '';

        return self::$cached = self::wellFormed($value) ? $value : self::DEFAULT;
    }

    public static function isCustom(): bool
    {
        return self::current() !== self::DEFAULT;
    }

    /** Full link of the admin login for a path, on this site. */
    public static function url(?string $path = null): string
    {
        return url('/'.($path ?? self::current()));
    }

    /**
     * Save a new path ("admin" puts the default back). Run validate() first.
     * Returns false when the route cache could not be cleared (the old path
     * would keep working until `php artisan route:clear`).
     */
    public static function set(string $path): bool
    {
        if ($path === self::DEFAULT) {
            return self::reset();
        }

        @mkdir(dirname(self::file()), 0775, true);
        file_put_contents(self::file(), $path."\n", LOCK_EX);
        self::$cached = $path;

        return self::clearRouteCache();
    }

    /** Back to /admin. */
    public static function reset(): bool
    {
        if (is_file(self::file())) {
            @unlink(self::file());
        }
        self::$cached = self::DEFAULT;

        return self::clearRouteCache();
    }

    /** `php artisan route:cache` would otherwise keep serving the old path. */
    private static function clearRouteCache(): bool
    {
        $cache = app()->getCachedRoutesPath();

        if (is_file($cache)) {
            @unlink($cache);
        }

        return ! is_file($cache);
    }

    /** Lowercase English letters, digits and single hyphens inside; 3–40 characters. */
    public static function wellFormed(string $path): bool
    {
        return strlen($path) >= self::MIN
            && strlen($path) <= self::MAX
            && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $path) === 1;
    }

    /** A Bangla error message, or null when the path can be used. */
    public static function validate(string $path): ?string
    {
        if ($path === self::DEFAULT) {
            return null; // puts the default back
        }

        if (! self::wellFormed($path)) {
            return __('শুধু ছোট হাতের ইংরেজি অক্ষর, সংখ্যা ও হাইফেন (-) চলবে — :min থেকে :max অক্ষর, হাইফেন দিয়ে শুরু বা শেষ নয়, পরপর দুটি হাইফেনও নয়।', ['min' => self::MIN, 'max' => self::MAX]);
        }

        if ($path === self::current()) {
            return null; // unchanged
        }

        if (in_array($path, self::RESERVED, true) || in_array($path, self::takenSegments(), true)) {
            return __('":path" নামটি সাইটের অন্য পেজ বা ফোল্ডারের নামের সাথে মিলে যায়। অন্য একটি নাম দিন।', ['path' => $path]);
        }

        return null;
    }

    /** First path segment of every route of the site (not the panel's own) and every name directly inside public/. */
    private static function takenSegments(): array
    {
        $panel = self::current();
        $segments = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $first = strtolower(explode('/', trim($route->uri(), '/'))[0]);

            if ($first !== '' && $first !== $panel && ! str_starts_with($first, '{')) {
                // sitemap.xml, robots.txt → also block the bare name
                $segments[] = $first;
                $segments[] = pathinfo($first, PATHINFO_FILENAME);
            }
        }

        foreach (@scandir(public_path()) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $segments[] = strtolower($entry);
                $segments[] = strtolower(pathinfo($entry, PATHINFO_FILENAME));
            }
        }

        return array_values(array_unique($segments));
    }
}
