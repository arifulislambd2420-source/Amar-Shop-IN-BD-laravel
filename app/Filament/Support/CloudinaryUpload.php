<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * A FileUpload field that stores images on the local `public` disk
 * (storage/app/public/media, requires the public/storage symlink — see
 * `php artisan storage:link`) instead of a remote service. Despite the
 * class name — kept as-is so every call site (ProductResource,
 * BannerResource, SiteSettings, LandingPageResource,
 * ImagesRelationManager) needs zero changes — this no longer talks to
 * Cloudinary at all.
 *
 * The field's stored state is still a full absolute URL string (e.g.
 * https://amarshopinbd.com/storage/media/<uuid>.webp), the exact same shape
 * Cloudinary's secure_url was, so every existing image/logo/icon column and
 * every blade view doing <img src="{{ $model->image }}"> keeps working
 * unchanged. Rows saved before this change still hold
 * https://res.cloudinary.com/... URLs — those images are untouched and
 * keep loading from Cloudinary; only new uploads go to the local disk.
 *
 * App\Services\CloudinaryService is intentionally left in place and
 * untouched (nothing here calls it anymore) — removing it/the Cloudinary
 * package/config is a separate, later cleanup.
 */
class CloudinaryUpload
{
    /** Relative to the `public` disk root (storage/app/public/). */
    public const DIRECTORY = 'media';

    public static function make(string $name): FileUpload
    {
        return FileUpload::make($name)
            ->image()
            ->imageEditor()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(5120) // 5 MB
            // The field's state is a full URL, not a bare disk-relative
            // path, so skip Filament's default disk-exists/size/mime
            // lookups (they'd try to match the URL string itself against a
            // file on disk and fail) and tell it how to preview an
            // already-stored URL instead.
            ->fetchFileInformation(false)
            ->getUploadedFileUsing(fn (?string $file): ?array => filled($file) ? [
                'name' => basename(parse_url($file, PHP_URL_PATH) ?: $file),
                'size' => 0,
                'type' => null,
                'url' => $file,
            ] : null)
            ->saveUploadedFileUsing(function ($file) {
                try {
                    // Random/unique filename — never trust or reuse the
                    // client's original name (collisions, overwrites, path
                    // tricks). Extension is safe to keep: Filament/->image()
                    // and acceptedFileTypes() above already validated the
                    // file before this callback ever runs.
                    $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();

                    $path = $file->storeAs(self::DIRECTORY, $filename, 'public');

                    return Storage::disk('public')->url($path);
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Image upload failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return null;
                }
            })
            ->helperText('Saved on the server (storage/app/public/media). JPG, PNG or WEBP, max 5 MB.');
    }
}
