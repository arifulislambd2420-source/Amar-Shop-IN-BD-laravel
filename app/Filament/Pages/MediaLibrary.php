<?php

namespace App\Filament\Pages;

use App\Filament\Support\CloudinaryUpload;
use App\Models\MediaLibrary as MediaLibraryItem;
use App\Services\CloudinaryService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * General-purpose media manager — not tied to any model/table. Uploads go
 * through the same App\Filament\Support\CloudinaryUpload helper as every
 * other image field in the app (Cloudinary, jpg/png/webp only, 5 MB max).
 *
 * The grid lists the Cloudinary uploads recorded in the media_library table
 * plus any legacy images still sitting on the local `public` disk
 * (storage/app/public/media) from before the switch to Cloudinary, so
 * nothing already uploaded disappears from the list. Run
 * `php artisan media:migrate-to-cloudinary` to move the legacy ones over.
 *
 * A top-level nav item on purpose (no navigationGroup) rather than nested
 * under Settings — this is a workspace, not a configuration page.
 */
class MediaLibrary extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Media Library';

    protected static ?string $title = 'Media Library';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.media-library';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Legacy local uploads: relative to the `public` disk root (storage/app/public/). */
    public const DIRECTORY = 'media';

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                CloudinaryUpload::make('uploads')
                    ->label('')
                    ->multiple()
                    ->helperText('JPG, PNG or WEBP, up to 5 MB each — select several files at once to upload them all.'),
            ]);
    }

    /**
     * Reading the form's state is what makes Filament actually run
     * CloudinaryUpload::saveUploadedFileUsing for each pending file (which
     * uploads it and records it in media_library). The returned URLs are
     * discarded; getFiles() reads the table afterwards.
     */
    public function upload(): void
    {
        $this->form->getState();
        $this->form->fill();

        Notification::make()->title('Images uploaded.')->success()->send();
    }

    /**
     * @return Collection<int, array{key: string, name: string, url: string, legacy: bool}>
     */
    public function getFiles(): Collection
    {
        $cloud = MediaLibraryItem::query()
            ->orderByDesc('id')
            ->get()
            ->filter(fn (MediaLibraryItem $m) => CloudinaryService::isCloudinaryUrl($m->file_path))
            ->map(fn (MediaLibraryItem $m): array => [
                'key' => 'c:'.$m->id,
                'name' => $m->file_name,
                'url' => $m->file_path,
                'legacy' => false,
                'at' => $m->created_at?->getTimestamp() ?? 0,
            ]);

        $disk = Storage::disk('public');

        $local = collect($disk->exists(self::DIRECTORY) ? $disk->files(self::DIRECTORY) : [])
            ->map(fn (string $path): array => [
                'key' => 'l:'.basename($path),
                'name' => basename($path),
                'url' => $disk->url($path),
                'legacy' => true,
                'at' => $disk->lastModified($path),
            ]);

        return $cloud->concat($local)->sortByDesc('at')->values();
    }

    /**
     * $key is a wire:click argument from the grid — untrusted input. It is
     * either "c:<id>" (a media_library row: the id is cast to int) or
     * "l:<basename>" (a legacy local file: any path separator or ".." is
     * rejected, so DIRECTORY.'/'.$basename can never leave that folder).
     */
    public function deleteFile(string $key): void
    {
        if (str_starts_with($key, 'c:')) {
            $this->deleteCloudinary((int) substr($key, 2));

            return;
        }

        abort_unless(str_starts_with($key, 'l:'), 403);

        $this->deleteLocal(substr($key, 2));
    }

    protected function deleteCloudinary(int $id): void
    {
        $item = MediaLibraryItem::find($id);

        if (! $item) {
            Notification::make()->title('File not found — it may already have been deleted.')->warning()->send();

            return;
        }

        if ($this->refuseIfInUse(basename(parse_url($item->file_path, PHP_URL_PATH) ?: $item->file_path))) {
            return;
        }

        try {
            if (! app(CloudinaryService::class)->deleteByUrl($item->file_path)) {
                Notification::make()->title('Cloudinary did not confirm the delete — not removed.')->danger()->send();

                return;
            }
        } catch (\Throwable $e) {
            Notification::make()->title('Delete failed')->body($e->getMessage())->danger()->send();

            return;
        }

        $item->delete();

        Notification::make()->title('Image deleted.')->success()->send();
    }

    protected function deleteLocal(string $basename): void
    {
        abort_if(
            $basename === '' || $basename !== basename($basename) || str_contains($basename, '..'),
            403,
        );

        $path = self::DIRECTORY.'/'.$basename;
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            Notification::make()->title('File not found — it may already have been deleted.')->warning()->send();

            return;
        }

        if ($this->refuseIfInUse($basename)) {
            return;
        }

        $disk->delete($path);

        Notification::make()->title('Image deleted.')->success()->send();
    }

    /** Sends the "still in use" notification and returns true when something references the file. */
    protected function refuseIfInUse(string $basename): bool
    {
        if (! $usedBy = $this->findReferences($basename)) {
            return false;
        }

        Notification::make()
            ->title('Not deleted — this image is still in use')
            ->body('Used by: '.implode(', ', $usedBy).'. Replace it there first, otherwise it will show as a broken image.')
            ->danger()
            ->persistent()
            ->send();

        return true;
    }
    /**
     * Every column that can hold an uploaded image URL. Deleting a file
     * that one of these still points to leaves a broken image on the live
     * site (this happened to a product image) — so deleteFile() refuses.
     *
     * @return list<string> human-readable "where it's used" labels
     */
    protected function findReferences(string $basename): array
    {
        $needle = '%'.$basename.'%';

        $checks = [
            'Product' => fn () => DB::table('products')->where('image', 'like', $needle)->pluck('name'),
            'Product gallery' => fn () => DB::table('product_images')->join('products', 'products.id', '=', 'product_images.product_id')->where('product_images.url', 'like', $needle)->pluck('products.name'),
            'Banner' => fn () => DB::table('banners')->where('image', 'like', $needle)->pluck('position'),
            'Brand' => fn () => DB::table('brands')->where('logo', 'like', $needle)->pluck('name'),
            'Category' => fn () => DB::table('categories')->where('icon', 'like', $needle)->pluck('name'),
            'Blog' => fn () => DB::table('blogs')->where('cover', 'like', $needle)->orWhere('content', 'like', $needle)->pluck('title'),
            'Landing page' => fn () => DB::table('landing_pages')->where(fn ($q) => $q->where('hero_image', 'like', $needle)->orWhere('gallery', 'like', $needle)->orWhere('description', 'like', $needle))->pluck('title'),
            'Site setting' => fn () => DB::table('site_settings')->where('setting_value', 'like', $needle)->pluck('setting_key'),
        ];

        $found = [];
        foreach ($checks as $label => $query) {
            foreach ($query() as $name) {
                $found[] = "{$label} \"{$name}\"";
            }
        }

        return $found;
    }
}
