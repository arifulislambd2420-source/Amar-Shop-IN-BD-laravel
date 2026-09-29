<?php

namespace App\Http\Controllers;

use App\Filament\Support\CloudinaryUpload;
use Illuminate\Support\Facades\Storage;

/**
 * Fallback for /storage/media/{file} when the public/storage symlink
 * doesn't exist. On this host `php artisan storage:link` fails (PHP's
 * symlink()/exec() are disabled), and without the link every image
 * uploaded since the switch away from Cloudinary 404s.
 *
 * When the symlink IS present the web server serves the file directly and
 * this route is never reached — it only runs for requests that would
 * otherwise 404. Registered without session/cookie middleware (see
 * routes/web.php) so an image request doesn't start a PHP session.
 */
class MediaFileController extends Controller
{
    public function show(string $file)
    {
        // Bare filename only — no "/", no "..": the resolved path can never
        // leave storage/app/public/media.
        abort_unless(preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $file) && ! str_contains($file, '..'), 404);

        $path = CloudinaryUpload::DIRECTORY.'/'.$file;
        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
