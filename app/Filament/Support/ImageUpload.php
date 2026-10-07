<?php

namespace App\Filament\Support;

use App\Models\MediaLibrary;
use App\Support\Media;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The one image-upload field used everywhere in the admin (products, product
 * gallery, banners, brands, categories, blog covers, landing pages, site
 * logo/favicon/OG image, media library).
 *
 * Files go to our own server: the `public` disk, config('media.directory'),
 * under a random name. The model column stores the site-relative URL
 * (/storage/media/<uuid>.<ext>), the same kind of value the columns always
 * held, so every <img src="{{ $model->image }}"> keeps working. Older values
 * (full https:// URLs) are left alone and still display.
 *
 * Only JPG / PNG / WEBP up to config('media.max_kb') are accepted — checked by
 * the browser, by Livewire's validation, and once more here against the real
 * file content (a renamed .exe or an SVG never gets stored).
 */
class ImageUpload
{
    public static function make(string $name): FileUpload
    {
        $maxKb = (int) config('media.max_kb');

        return FileUpload::make($name)
            ->image()
            ->imageEditor()
            ->acceptedFileTypes(array_keys(config('media.mimes')))
            ->maxSize($maxKb)
            // The state is a URL, not a disk path, so skip Filament's own
            // exists/size/mime lookups and preview the stored URL directly.
            ->fetchFileInformation(false)
            ->getUploadedFileUsing(fn (?string $file): ?array => filled($file) ? [
                'name' => basename(parse_url($file, PHP_URL_PATH) ?: $file),
                'size' => 0,
                'type' => null,
                'url' => $file,
            ] : null)
            ->saveUploadedFileUsing(function ($file) {
                try {
                    return self::store($file);
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('ছবি আপলোড হয়নি')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return null;
                }
            })
            ->helperText('JPG, PNG বা WEBP — সর্বোচ্চ '.self::maxLabel().'। ছবি আমাদের নিজের সার্ভারে সেভ হয়।');
    }

    /**
     * Save an uploaded image and return its site-relative URL.
     *
     * @throws RuntimeException when the file is not an allowed image or is too big
     */
    public static function store(UploadedFile $file): string
    {
        $ext = self::validatedExtension($file->getRealPath(), $file->getSize());
        $directory = config('media.directory');
        $name = Str::uuid()->toString().'.'.$ext;

        $path = Storage::disk(config('media.disk'))->putFileAs($directory, $file, $name);

        if (! $path) {
            throw new RuntimeException('ফাইলটি সার্ভারে লেখা যায়নি (storage ফোল্ডারের permission দেখুন)।');
        }

        Media::mirror($path);
        $url = Media::urlFor($path);

        MediaLibrary::create([
            'file_name' => mb_substr($file->getClientOriginalName() ?: $name, 0, 255),
            'file_path' => $url,
            'mime_type' => array_search($ext, config('media.mimes'), true) ?: null,
            'file_size' => $file->getSize(),
        ]);

        return $url;
    }

    /**
     * Same as store(), for a file that is already on disk (e.g. downloaded by
     * `media:localize-remote`). Same checks, same storage, same result.
     *
     * @throws RuntimeException
     */
    public static function storeFromPath(string $path, string $originalName): string
    {
        $ext = self::validatedExtension($path);
        $name = Str::uuid()->toString().'.'.$ext;

        $stored = Storage::disk(config('media.disk'))->putFileAs(config('media.directory'), new \Illuminate\Http\File($path), $name);

        if (! $stored) {
            throw new RuntimeException('ফাইলটি সার্ভারে লেখা যায়নি (storage ফোল্ডারের permission দেখুন)।');
        }

        Media::mirror($stored);
        $url = Media::urlFor($stored);

        MediaLibrary::create([
            'file_name' => mb_substr($originalName ?: $name, 0, 255),
            'file_path' => $url,
            'mime_type' => array_search($ext, config('media.mimes'), true) ?: null,
            'file_size' => (int) @filesize($path),
        ]);

        return $url;
    }

    /**
     * Inspect the actual bytes (not the name or the browser's claim) and return
     * the extension to save with.
     *
     * @throws RuntimeException
     */
    public static function validatedExtension(string $path, ?int $bytes = null): string
    {
        $bytes ??= (int) @filesize($path);

        if ($bytes > config('media.max_kb') * 1024) {
            throw new RuntimeException('ছবিটি অনেক বড় — সর্বোচ্চ '.self::maxLabel().'।');
        }

        $info = @getimagesize($path);
        $mime = $info['mime'] ?? null;
        $mimes = config('media.mimes');

        if (! $mime || ! isset($mimes[$mime])) {
            throw new RuntimeException('শুধু JPG, PNG বা WEBP ছবি আপলোড করা যায়।');
        }

        return $mimes[$mime];
    }

    /** Rich-text editors: pasted/attached images go to the same place, same rules. */
    public static function configureRichEditor(RichEditor $editor): RichEditor
    {
        return $editor
            ->fileAttachmentsDisk(config('media.disk'))
            ->fileAttachmentsDirectory(config('media.directory'))
            ->fileAttachmentsVisibility('public')
            ->fileAttachmentsAcceptedFileTypes(array_keys(config('media.mimes')))
            ->fileAttachmentsMaxSize((int) config('media.max_kb'));
    }

    private static function maxLabel(): string
    {
        $kb = (int) config('media.max_kb');

        return $kb >= 1024 ? rtrim(rtrim(number_format($kb / 1024, 1), '0'), '.').' MB' : $kb.' KB';
    }
}
