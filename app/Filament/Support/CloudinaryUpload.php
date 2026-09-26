<?php

namespace App\Filament\Support;

use App\Services\CloudinaryService;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Throwable;

/**
 * A FileUpload field, preconfigured so the uploaded image is sent to
 * Cloudinary (folder: amarshopbd, matching the old app) and the field's
 * stored state is the resulting secure_url string — the same plain
 * varchar URL the existing image/logo/icon columns already expect, so no
 * DB schema change is needed.
 *
 * If Cloudinary credentials are not set (e.g. local dev), the field still
 * renders normally; only the actual upload action fails, with a Filament
 * notification instead of a crash.
 */
class CloudinaryUpload
{
    public static function make(string $name): FileUpload
    {
        return FileUpload::make($name)
            ->image()
            ->imageEditor()
            ->maxSize(5120)
            // The field's state is a full Cloudinary secure_url, not a path
            // on any Laravel-managed disk, so skip Filament's default
            // disk-exists/size/mime lookups (they'd fail against a remote
            // URL) and tell it how to preview an already-stored URL.
            ->fetchFileInformation(false)
            ->getUploadedFileUsing(fn (?string $file): ?array => filled($file) ? [
                'name' => basename(parse_url($file, PHP_URL_PATH) ?: $file),
                'size' => 0,
                'type' => null,
                'url' => $file,
            ] : null)
            ->saveUploadedFileUsing(function ($file) {
                try {
                    return app(CloudinaryService::class)->upload($file);
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Cloudinary upload failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return null;
                }
            })
            ->helperText('Uploads to Cloudinary (folder: amarshopbd). Without CLOUDINARY_* env vars set, uploads will fail — expected in local dev.');
    }
}
