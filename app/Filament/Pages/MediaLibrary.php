<?php

namespace App\Filament\Pages;

use App\Filament\Support\CloudinaryUpload;
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
 * through the exact same App\Filament\Support\CloudinaryUpload helper as
 * every other image field in the app (storage/app/public/media, UUID
 * filenames, jpg/png/webp only, 5 MB max), so a file uploaded here is
 * physically indistinguishable from one uploaded via, say, ProductResource.
 * The grid below is always a fresh directory listing — nothing about
 * "which files exist" is tracked anywhere else, so there's nothing to keep
 * in sync and no migration is needed.
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

    /** Relative to the `public` disk root (storage/app/public/) — the same folder CloudinaryUpload writes to. */
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
     * Reading the form's state is what makes Filament actually persist any
     * pending temporary uploads to disk (CloudinaryUpload::saveUploadedFileUsing
     * runs per file here). The URLs it returns are discarded on purpose —
     * they're not stored anywhere; getFiles() below just re-scans the
     * directory afterwards, which is simpler than trying to keep a separate
     * list in sync.
     */
    public function upload(): void
    {
        $this->form->getState();
        $this->form->fill();

        Notification::make()->title('Images uploaded.')->success()->send();
    }

    /**
     * @return Collection<int, array{basename: string, url: string}>
     */
    public function getFiles(): Collection
    {
        $disk = Storage::disk('public');

        return collect($disk->files(self::DIRECTORY))
            ->sortByDesc(fn (string $path): int => $disk->lastModified($path))
            ->values()
            ->map(fn (string $path): array => [
                'basename' => basename($path),
                'url' => $disk->url($path),
            ]);
    }

    /**
     * $basename is a wire:click argument from the grid — untrusted input.
     * Rejecting anything containing a path separator or ".." means the
     * value that reaches Storage::delete() can never resolve outside
     * self::DIRECTORY: with no "/" allowed in $basename at all,
     * DIRECTORY.'/'.$basename cannot become a path to any other directory,
     * no matter what string is sent.
     */
    public function deleteFile(string $basename): void
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

        if ($usedBy = $this->findReferences($basename)) {
            Notification::make()
                ->title('Not deleted — this image is still in use')
                ->body('Used by: '.implode(', ', $usedBy).'. Replace it there first, otherwise it will show as a broken image.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $disk->delete($path);

        Notification::make()->title('Image deleted.')->success()->send();
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
