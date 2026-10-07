<?php

/*
| Uploaded images (admin panel). Files are stored on our own server, on the
| `public` disk (storage/app/public/<directory>), and referenced from the
| database by a site-relative URL such as /storage/media/<uuid>.webp — so a
| domain change or a wrong APP_URL never breaks them.
|
| /storage/... is normally served straight from public/storage (the symlink
| that `php artisan storage:link` creates). If the host can't create that
| symlink, App\Http\Controllers\MediaFileController serves the same URLs
| through Laravel instead — nothing else changes.
*/

return [
    'disk' => 'public',

    'directory' => 'media',

    // Where the web server looks for /storage/... (the storage:link symlink, or the
    // mirrored copies when symlinks are blocked — see App\Support\Media).
    'public_path' => public_path('storage'),

    // Largest accepted upload, in kilobytes (default 5 MB).
    'max_kb' => (int) env('MEDIA_MAX_KB', 5120),

    // Only real raster photos. SVG is excluded on purpose (it can carry script).
    'mimes' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],
];
