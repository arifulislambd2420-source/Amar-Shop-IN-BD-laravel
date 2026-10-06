<?php

namespace App\Filament\Support;

use App\Models\MediaLibrary;
use App\Services\CloudinaryService;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Throwable;

/**
 * A FileUpload field that uploads images to Cloudinary through
 * App\Services\CloudinaryService (folder from CLOUDINARY_FOLDER) and stores
 * the returned secure_url — a full https://res.cloudinary.com/... URL — in
 * the model column. Every call site (ProductResource, BannerResource,
 * SiteSettings, LandingPageResource, ImagesRelationManager, MediaLibrary)
 * goes through here, so there is a single upload path.
 *
 * Each upload is also recorded in the media_library table (URL, mime, size)
 * so the Media Library page can list it.
 *
 * Images uploaded before this switch live on the local `public` disk
 * (storage/app/public/media) and are referenced by /storage/media/... URLs.
 * Those keep working unchanged (the URL in the DB is just rendered as-is;
 * see MediaFileController for the no-symlink fallback), and
 * `php artisan media:migrate-to-cloudinary` moves them to Cloudinary.
 */
class CloudinaryUpload
{
    /** Legacy local uploads: relative to the `public` disk root (storage/app/public/). */
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
                    $uploaded = app(CloudinaryService::class)->uploadWithMeta($file);

                    MediaLibrary::create([
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $uploaded['url'],
                        'mime_type' => $uploaded['mime'],
                        'file_size' => $uploaded['bytes'],
                    ]);

                    return $uploaded['url'];
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Image upload failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return null;
                }
            })
            ->helperText('Uploaded to Cloudinary. JPG, PNG or WEBP, max 5 MB.');
    }
}
