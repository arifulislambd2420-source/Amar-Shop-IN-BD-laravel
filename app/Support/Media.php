<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Helpers for stored image URLs. Uploads are saved as site-relative paths
 * (/storage/media/x.webp); places that must hand a full URL to someone else
 * (Open Graph tags, JSON-LD, product feeds) go through absolute().
 *
 * How /storage/... reaches the browser, in order of preference:
 *  1. public/storage is a symlink to storage/app/public (`php artisan
 *     storage:link`) — the web server serves the files directly.
 *  2. Symlinks are blocked on the host: uploads are also *mirrored* as real
 *     files into public/storage (see mirror()), and the web server serves
 *     those directly. `php artisan media:link` sets this up and copies
 *     existing files.
 *  3. Neither exists: routes/web.php serves /storage/media/{file} through
 *     Laravel (MediaFileController) — slower, but never a broken image.
 */
class Media
{
    /** Full URL for an image reference: relative paths get the current site URL. */
    public static function absolute(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $url)) {
            return str_starts_with($url, '//') ? 'https:'.$url : $url;
        }

        return url('/'.ltrim($url, '/'));
    }

    /** Public URL path for a file stored in the media directory. */
    public static function urlFor(string $diskPath): string
    {
        return '/storage/'.ltrim($diskPath, '/');
    }

    /** True when public/storage is NOT the usual symlink, so files must be copied there. */
    public static function needsMirror(): bool
    {
        return ! is_link(config('media.public_path'));
    }

    /** Copy a stored file into public/storage so the web server can serve it (no-op with a symlink). */
    public static function mirror(string $diskPath): bool
    {
        if (! self::needsMirror()) {
            return true;
        }

        try {
            $target = self::publicTarget($diskPath);
            $dir = dirname($target);

            if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                return false;
            }

            return @copy(Storage::disk(config('media.disk'))->path($diskPath), $target);
        } catch (Throwable) {
            return false;
        }
    }

    /** Remove the mirrored copy of a deleted file. */
    public static function unmirror(string $diskPath): void
    {
        if (self::needsMirror()) {
            @unlink(self::publicTarget($diskPath));
        }
    }

    private static function publicTarget(string $diskPath): string
    {
        return rtrim((string) config('media.public_path'), "/\\").'/'.ltrim($diskPath, '/');
    }
}
