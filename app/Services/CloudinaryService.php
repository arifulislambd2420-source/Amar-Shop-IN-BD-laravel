<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Thin wrapper around the Cloudinary PHP SDK (cloudinary/cloudinary_php).
 *
 * The Laravel-specific cloudinary-labs/cloudinary-laravel package does not
 * yet support Laravel 13 (its latest release caps at ^12.0), so we talk to
 * the vendor SDK directly and expose just what the admin panel needs:
 * upload a file and get back its secure_url, which is what gets stored in
 * the existing varchar image/logo/icon columns (same as the old Next.js
 * app's /api/admin/upload route).
 */
class CloudinaryService
{
    public function configured(): bool
    {
        return filled(config('services.cloudinary.cloud_name'))
            && filled(config('services.cloudinary.api_key'))
            && filled(config('services.cloudinary.api_secret'));
    }

    protected function client(): Cloudinary
    {
        return new Cloudinary([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
            'url' => ['secure' => true],
        ]);
    }

    /**
     * Upload a file (a Livewire/Filament temporary upload, or a plain path)
     * to the configured Cloudinary folder (CLOUDINARY_FOLDER) and return its secure_url.
     */
    public function upload(UploadedFile|string $file): string
    {
        return $this->uploadWithMeta($file)['url'];
    }

    /**
     * Same as upload(), but also returns what the Media Library records.
     *
     * @return array{url: string, public_id: string, bytes: int, mime: string}
     */
    public function uploadWithMeta(UploadedFile|string $file): array
    {
        if (! $this->configured()) {
            throw new RuntimeException(
                'Cloudinary is not configured. Set CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY and '
                .'CLOUDINARY_API_SECRET (or CLOUDINARY_URL) in .env before uploading images.'
            );
        }

        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        $result = $this->client()->uploadApi()->upload($path, [
            'folder' => config('services.cloudinary.folder'),
            'resource_type' => 'image',
        ]);

        return [
            'url' => (string) $result['secure_url'],
            'public_id' => (string) $result['public_id'],
            'bytes' => (int) ($result['bytes'] ?? 0),
            'mime' => 'image/'.($result['format'] ?? 'jpeg'),
        ];
    }

    /** True for an image URL hosted on Cloudinary (as opposed to a legacy local one). */
    public static function isCloudinaryUrl(?string $url): bool
    {
        return is_string($url) && str_contains($url, 'res.cloudinary.com/');
    }

    /**
     * Cloudinary public_id from a delivery URL
     * (https://res.cloudinary.com/<cloud>/image/upload/[transforms/]v123/<folder>/<id>.<ext>).
     */
    public static function publicIdFromUrl(string $url): ?string
    {
        $after = parse_url($url, PHP_URL_PATH);
        $pos = is_string($after) ? strpos($after, '/image/upload/') : false;

        if ($pos === false) {
            return null;
        }

        $segments = explode('/', substr($after, $pos + strlen('/image/upload/')));

        // Everything up to and including the "v<digits>" version segment is
        // delivery transformations/version, not part of the public_id.
        foreach ($segments as $i => $segment) {
            if (preg_match('/^v\d+$/', $segment)) {
                $segments = array_slice($segments, $i + 1);
                break;
            }
        }

        $publicId = preg_replace('/\.[A-Za-z0-9]+$/', '', rawurldecode(implode('/', $segments)));

        return $publicId !== '' ? $publicId : null;
    }

    /** Delete an uploaded image by its URL. Returns true when Cloudinary reports it gone. */
    public function deleteByUrl(string $url): bool
    {
        $publicId = self::publicIdFromUrl($url);

        if (! $publicId || ! $this->configured()) {
            return false;
        }

        $result = $this->client()->uploadApi()->destroy($publicId, ['invalidate' => true, 'resource_type' => 'image']);

        return in_array($result['result'] ?? null, ['ok', 'not found'], true);
    }
}
