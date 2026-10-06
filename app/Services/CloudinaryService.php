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
        if (! $this->configured()) {
            throw new RuntimeException(
                'Cloudinary is not configured. Set CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY and '
                .'CLOUDINARY_API_SECRET (or CLOUDINARY_URL) in .env before uploading images.'
            );
        }

        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        $result = $this->client()->uploadApi()->upload($path, [
            'folder' => config('services.cloudinary.folder'),
        ]);

        return (string) $result['secure_url'];
    }
}
