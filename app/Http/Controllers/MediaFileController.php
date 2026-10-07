<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

/**
 * Serves uploaded images at /storage/media/{file} when the public/storage
 * symlink is missing — on shared hosting `php artisan storage:link` often
 * fails because PHP's symlink()/exec() are disabled.
 *
 * When the symlink IS present the web server serves the file directly and
 * this route is never reached; it only runs for requests that would
 * otherwise 404. Registered without session/cookie middleware (see
 * routes/web.php) so an image request doesn't start a PHP session.
 */
class MediaFileController extends Controller
{
    public function show(string $file)
    {
        // Bare filename only — no "/", no "..": the resolved path can never
        // leave the media directory.
        abort_unless(preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $file) && ! str_contains($file, '..'), 404);

        $path = config('media.directory').'/'.$file;
        $disk = Storage::disk(config('media.disk'));

        abort_unless($disk->exists($path), 404);

        // Only ever hand out images, with the type taken from the file itself.
        $mime = $disk->mimeType($path) ?: 'application/octet-stream';
        abort_unless(str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml', 404);

        return response()->file($disk->path($path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
