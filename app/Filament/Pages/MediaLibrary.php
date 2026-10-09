<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminArea;
use App\Filament\Support\ImageUpload;
use App\Models\MediaLibrary as MediaLibraryItem;
use App\Support\Media;
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
 * through the same App\Filament\Support\ImageUpload helper as every other
 * image field (our own server, jpg/png/webp only, size-limited).
 *
 * The grid is a listing of the media directory on disk — the files that
 * actually exist — with the original file names taken from the
 * media_library table where known.
 *
 * A top-level nav item on purpose (no navigationGroup) rather than nested
 * under Settings — this is a workspace, not a configuration page.
 */
class MediaLibrary extends Page implements HasSchemas
{
    use HasAdminArea;
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'মিডিয়া লাইব্রেরি';

    protected static ?string $title = 'মিডিয়া লাইব্রেরি';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.media-library';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                ImageUpload::make('uploads')
                    ->label('')
                    ->multiple(),
            ]);
    }

    /**
     * Reading the form's state is what makes Filament actually run
     * ImageUpload::saveUploadedFileUsing for each pending file. The returned
     * URLs are discarded; getFiles() lists the directory afterwards.
     */
    public function upload(): void
    {
        $this->form->getState();
        $this->form->fill();

        Notification::make()->title('ছবি আপলোড হয়েছে।')->success()->send();
    }

    /**
     * @return Collection<int, array{key: string, name: string, url: string}>
     */
    public function getFiles(): Collection
    {
        $disk = Storage::disk(config('media.disk'));
        $directory = config('media.directory');

        if (! $disk->exists($directory)) {
            return collect();
        }

        $names = MediaLibraryItem::query()->pluck('file_name', 'file_path');

        return collect($disk->files($directory))
            ->sortByDesc(fn (string $path): int => $disk->lastModified($path))
            ->values()
            ->map(function (string $path) use ($names): array {
                $url = Media::urlFor($path);

                return [
                    'key' => basename($path),
                    'name' => $names[$url] ?? basename($path),
                    'url' => $url,
                ];
            });
    }

    /**
     * $basename is a wire:click argument from the grid — untrusted input. Any
     * path separator or ".." is rejected, so the path that reaches
     * Storage::delete() can never leave the media directory.
     */
    public function deleteFile(string $basename): void
    {
        abort_if(
            $basename === '' || $basename !== basename($basename) || str_contains($basename, '..'),
            403,
        );

        $path = config('media.directory').'/'.$basename;
        $disk = Storage::disk(config('media.disk'));

        if (! $disk->exists($path)) {
            Notification::make()->title('ফাইলটি পাওয়া যায়নি — হয়তো আগেই মোছা হয়েছে।')->warning()->send();

            return;
        }

        if ($usedBy = $this->findReferences($basename)) {
            Notification::make()
                ->title('মোছা হয়নি — ছবিটি এখনো ব্যবহার হচ্ছে')
                ->body('ব্যবহার হচ্ছে: '.implode(', ', $usedBy).'। আগে সেখান থেকে বদলান, নইলে সাইটে ভাঙা ছবি দেখাবে।')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $disk->delete($path);
        Media::unmirror($path);
        MediaLibraryItem::where('file_path', Media::urlFor($path))->delete();

        Notification::make()->title('ছবি মোছা হয়েছে।')->success()->send();
    }

    /**
     * Every column that can hold an uploaded image URL. Deleting a file that
     * one of these still points to leaves a broken image on the live site,
     * so deleteFile() refuses.
     *
     * @return list<string> human-readable "where it's used" labels
     */
    protected function findReferences(string $basename): array
    {
        $needle = '%'.$basename.'%';

        $checks = [
            'পণ্য' => fn () => DB::table('products')->where(fn ($q) => $q->where('image', 'like', $needle)->orWhere('description', 'like', $needle))->pluck('name'),
            'পণ্যের গ্যালারি' => fn () => DB::table('product_images')->join('products', 'products.id', '=', 'product_images.product_id')->where('product_images.url', 'like', $needle)->pluck('products.name'),
            'ব্যানার' => fn () => DB::table('banners')->where('image', 'like', $needle)->pluck('position'),
            'ব্র্যান্ড' => fn () => DB::table('brands')->where('logo', 'like', $needle)->pluck('name'),
            'ক্যাটাগরি' => fn () => DB::table('categories')->where('icon', 'like', $needle)->pluck('name'),
            'ব্লগ' => fn () => DB::table('blogs')->where('cover', 'like', $needle)->orWhere('content', 'like', $needle)->pluck('title'),
            'ল্যান্ডিং পেজ' => fn () => DB::table('landing_pages')->where(fn ($q) => $q
                ->where('hero_image', 'like', $needle)
                ->orWhere('gallery', 'like', $needle)
                ->orWhere('description', 'like', $needle)
                ->orWhere('blocks', 'like', $needle)
                ->orWhere('packages', 'like', $needle)
                ->orWhere('og_image', 'like', $needle))->pluck('title'),
            'সাইট সেটিং' => fn () => DB::table('site_settings')->where('setting_value', 'like', $needle)->pluck('setting_key'),
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
