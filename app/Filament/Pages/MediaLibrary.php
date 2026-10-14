<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminArea;
use App\Filament\Support\ImageUpload;
use App\Models\MediaLibrary as MediaLibraryItem;
use App\Support\Media;
use App\Support\MediaDedupe;
use App\Support\MediaReferences;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

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

    public static function getNavigationLabel(): string
    {

        return __('মিডিয়া লাইব্রেরি');

    }

    public function getTitle(): string|Htmlable
    {

        return __('মিডিয়া লাইব্রেরি');

    }

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

        Notification::make()->title(__('ছবি আপলোড হয়েছে।'))->success()->send();
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

    /** The picture the admin asked to delete and is being asked to confirm (null = no dialog open). */
    public ?array $pending = null;

    /** Step 1 of deleting: show what the picture is used in, and ask. */
    public function askDelete(string $basename): void
    {
        $this->assertSafeBasename($basename);

        if (! Storage::disk(config('media.disk'))->exists(config('media.directory').'/'.$basename)) {
            Notification::make()->title(__('ফাইলটি পাওয়া যায়নি — হয়তো আগেই মোছা হয়েছে।'))->warning()->send();

            return;
        }

        $this->pending = ['key' => $basename, 'places' => MediaReferences::labels($basename)];
    }

    public function cancelDelete(): void
    {
        $this->pending = null;
    }

    /** Step 2: the admin confirmed — delete, leaving any place that used the picture empty. */
    public function confirmDelete(): void
    {
        if ($this->pending) {
            $this->deleteFile($this->pending['key'], force: true);
        }

        $this->pending = null;
    }

    /**
     * $basename is a wire:click argument from the grid — untrusted input. Any
     * path separator or ".." is rejected, so the path that reaches
     * Storage::delete() can never leave the media directory.
     *
     * A picture that is still used is only deleted with $force (the confirmed
     * dialog): the places that used it are then emptied, never left broken.
     */
    public function deleteFile(string $basename, bool $force = false): bool
    {
        $this->assertSafeBasename($basename);

        $path = config('media.directory').'/'.$basename;
        $disk = Storage::disk(config('media.disk'));

        if (! $disk->exists($path)) {
            Notification::make()->title(__('ফাইলটি পাওয়া যায়নি — হয়তো আগেই মোছা হয়েছে।'))->warning()->send();

            return false;
        }

        $places = MediaReferences::labels($basename);

        if ($places && ! $force) {
            Notification::make()
                ->title(__('মোছা হয়নি — ছবিটি এখনো ব্যবহার হচ্ছে'))
                ->body(__('ব্যবহার হচ্ছে: :places। আগে সেখান থেকে বদলান, নইলে সাইটে ভাঙা ছবি দেখাবে।', ['places' => implode(', ', $places)]))
                ->danger()
                ->persistent()
                ->send();

            return false;
        }

        if ($places) {
            MediaReferences::blank($basename);
        }

        $disk->delete($path);
        Media::unmirror($path);
        MediaLibraryItem::where('file_path', Media::urlFor($path))->delete();

        Notification::make()
            ->title(__('ছবি মোছা হয়েছে।'))
            ->body($places ? __('এই জায়গাগুলোর ছবির ঘর এখন ফাঁকা: :places', ['places' => implode(', ', $places)]) : null)
            ->success()
            ->send();

        return true;
    }

    private function assertSafeBasename(string $basename): void
    {
        abort_if(
            $basename === '' || $basename !== basename($basename) || str_contains($basename, '..'),
            403,
        );
    }

    /** Header button: merge identical pictures (one copy each, every link kept). */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('mergeDuplicates')
                ->label(__('ডুপ্লিকেট ছবি একত্র করুন'))
                ->icon(Heroicon::OutlinedSquare2Stack)
                ->color('gray')
                ->modalHeading(__('ডুপ্লিকেট ছবি একত্র করবেন?'))
                ->modalDescription(function (): string {
                    MediaDedupe::hashPending(100000);
                    $plan = MediaDedupe::plan();

                    if ($plan === []) {
                        return __('একই ছবির কোনো ডুপ্লিকেট নেই।');
                    }

                    return __(':groups টি ছবির :copies টি বাড়তি কপি আছে (:size)। প্রতিটির সবচেয়ে পুরনোটি রাখা হবে, যেখানে বাড়তি কপি ব্যবহার হচ্ছিল সেখানকার লিংক নিজে থেকেই ঠিক হবে, তারপর বাড়তি কপি মোছা হবে।', [
                        'groups' => count($plan),
                        'copies' => array_sum(array_map(fn ($g) => count($g['copies']), $plan)),
                        'size' => Number::fileSize(array_sum(array_column($plan, 'bytes'))),
                    ]);
                })
                ->modalSubmitActionLabel(__('একত্র করুন'))
                ->requiresConfirmation()
                ->action(function (): void {
                    $stats = MediaDedupe::merge();

                    Notification::make()
                        ->title($stats['removed'] ? __(':n টি বাড়তি কপি একত্র করা হয়েছে।', ['n' => $stats['removed']]) : __('একত্র করার মতো কিছু ছিল না।'))
                        ->body($stats['removed'] ? __(':n জায়গার লিংক ঠিক করা হয়েছে, :size জায়গা বাঁচল।', ['n' => $stats['updated'], 'size' => Number::fileSize($stats['bytes'])]) : null)
                        ->success()
                        ->send();
                }),
        ];
    }
}
