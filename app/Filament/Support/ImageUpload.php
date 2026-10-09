<?php

namespace App\Filament\Support;

use App\Models\MediaLibrary;
use App\Support\Media;
use App\Support\RemoteImage;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Http\File;
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
 *
 * Three ways to put a picture in a field:
 *  1. upload from the computer (the field itself),
 *  2. pick one already in the Media Library ("লাইব্রেরি থেকে বাছুন"),
 *  3. paste a link ("লিংক থেকে নিন") — the server downloads it (safely, see
 *     RemoteImage), checks it like an upload and stores it on our own server.
 * So whichever way it comes in, the stored value is a local /storage/media/… URL.
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
            ->placeholder(self::DROP_LABEL)
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
            ->hintActions([self::libraryAction(), self::linkAction()])
            ->helperText('কম্পিউটার থেকে আপলোড করুন, অথবা উপরের বাটনে লাইব্রেরি থেকে বাছুন / লিংক দিন। JPG, PNG বা WEBP — সর্বোচ্চ '.self::maxLabel().'।');
    }

    /** The drop zone's text (FilePond has no Bangla pack of its own, so it is set here). */
    private const DROP_LABEL = 'ছবি এখানে টেনে আনুন, অথবা <span class="filepond--label-action">কম্পিউটার থেকে বাছুন</span>';

    // ── Source 2: the Media Library ─────────────────────────────────────

    /** How many of the newest pictures the picker shows (it is searchable by file name). */
    private const LIBRARY_LIMIT = 120;

    /** url => HTML tile (picture + file name) for the picker. */
    public static function libraryOptions(): array
    {
        return MediaLibrary::query()
            ->latest('id')
            ->limit(self::LIBRARY_LIMIT)
            ->get(['file_name', 'file_path'])
            ->mapWithKeys(fn (MediaLibrary $m) => [$m->file_path => '<span class="block"><img src="'.e($m->file_path).'" alt="" loading="lazy" class="mb-1 h-20 w-20 rounded-lg object-cover"><span class="block max-w-[5rem] truncate text-xs">'.e($m->file_name).'</span></span>'])
            ->all();
    }

    /** Only the chosen URLs that really are in the Media Library (a submitted form could name anything). */
    public static function onlyLibraryUrls(array $urls): array
    {
        return MediaLibrary::whereIn('file_path', array_values($urls))->pluck('file_path')->all();
    }

    /** The picker: tick one or several pictures from the library. */
    public static function libraryPicker(string $name = 'urls'): CheckboxList
    {
        return CheckboxList::make($name)
            ->hiddenLabel()
            ->options(fn () => self::libraryOptions())
            ->allowHtml()
            ->searchable()
            ->searchPrompt('ফাইলের নাম লিখে খুঁজুন')
            ->noSearchResultsMessage('এই নামে কোনো ছবি নেই')
            ->columns(['default' => 2, 'sm' => 3, 'md' => 4])
            ->bulkToggleable(false);
    }

    public static function libraryAction(): Action
    {
        return Action::make('pickFromLibrary')
            ->label('লাইব্রেরি থেকে বাছুন')
            ->icon('heroicon-o-photo')
            ->link()
            ->modalHeading('মিডিয়া লাইব্রেরি থেকে ছবি বাছুন')
            ->modalSubmitActionLabel('এই ছবি ব্যবহার করুন')
            ->modalCancelActionLabel('বন্ধ করুন')
            ->modalWidth('4xl')
            ->schema(fn (FileUpload $component) => [
                self::libraryPicker()->required()->maxItems($component->isMultiple() ? null : 1),
            ])
            ->action(function (array $data, FileUpload $component): void {
                $urls = self::onlyLibraryUrls($data['urls'] ?? []);

                if ($urls === []) {
                    Notification::make()->title('কোনো ছবি বাছা হয়নি')->warning()->send();

                    return;
                }

                self::putInto($component, $urls);
            });
    }

    // ── Source 3: a link ────────────────────────────────────────────────

    public static function linkAction(): Action
    {
        return Action::make('pasteLink')
            ->label('লিংক থেকে নিন')
            ->icon('heroicon-o-link')
            ->link()
            ->modalHeading('ছবির লিংক বসান')
            ->modalDescription('ছবিটি আমাদের সার্ভারে কপি হয়ে যাবে, তাই অন্য সাইট বন্ধ হলেও ছবি থাকবে।')
            ->modalSubmitActionLabel('ছবি আনুন')
            ->modalCancelActionLabel('বন্ধ করুন')
            ->schema([self::linkInput()->required()])
            ->action(function (array $data, FileUpload $component): void {
                try {
                    $url = self::storeFromUrl((string) ($data['url'] ?? ''));
                } catch (RuntimeException $e) {
                    Notification::make()->title('ছবি আনা যায়নি')->body($e->getMessage())->danger()->send();

                    return;
                }

                self::putInto($component, [$url]);
            });
    }

    public static function linkInput(string $name = 'url'): TextInput
    {
        return TextInput::make($name)
            ->label('ছবির লিংক')
            ->placeholder('https://example.com/photo.jpg')
            ->url()
            ->maxLength(1000);
    }

    /**
     * Download an image from a link and keep it on our own server.
     *
     * @return string the stored image's local URL
     *
     * @throws RuntimeException (Bangla message) when the link is unusable, not an allowed image, or too big
     */
    public static function storeFromUrl(string $url): string
    {
        $tmp = RemoteImage::download($url);

        try {
            return self::storeFromPath($tmp, basename((string) parse_url($url, PHP_URL_PATH)));
        } finally {
            @unlink($tmp);
        }
    }

    /** Put picked / fetched URLs into the field: replaces a single picture, adds to a gallery. */
    private static function putInto(FileUpload $component, array $urls): void
    {
        if ($component->isMultiple()) {
            $component->state(array_values(array_unique(array_merge((array) $component->getState(), $urls))));

            return;
        }

        $component->state($urls[0]);
    }

    // ── Rich editors: add a picture to the text ─────────────────────────

    /** A button above a rich editor that adds a picture (upload, library or link) at the end of the text. */
    public static function insertImageAction(): Action
    {
        return Action::make('insertImage')
            ->label('ছবি যোগ করুন')
            ->icon('heroicon-o-photo')
            ->link()
            ->modalHeading('লেখায় ছবি যোগ করুন')
            ->modalSubmitActionLabel('ছবি যোগ করুন')
            ->modalCancelActionLabel('বন্ধ করুন')
            ->modalWidth('4xl')
            ->schema([
                Tabs::make('source')->tabs([
                    Tab::make('কম্পিউটার থেকে')->icon('heroicon-o-arrow-up-tray')->schema([
                        FileUpload::make('upload')
                            ->hiddenLabel()
                            ->image()
                            ->acceptedFileTypes(array_keys(config('media.mimes')))
                            ->maxSize((int) config('media.max_kb'))
                            ->fetchFileInformation(false)
                            ->placeholder(self::DROP_LABEL)
                            ->saveUploadedFileUsing(fn ($file) => self::storeOrNotify($file))
                            ->helperText('JPG, PNG বা WEBP — সর্বোচ্চ '.self::maxLabel().'।'),
                    ]),
                    Tab::make('লাইব্রেরি থেকে')->icon('heroicon-o-photo')->schema([
                        self::libraryPicker('library')->maxItems(1),
                    ]),
                    Tab::make('লিংক থেকে')->icon('heroicon-o-link')->schema([
                        self::linkInput('link'),
                    ]),
                ]),
            ])
            ->action(function (array $data, RichEditor $component): void {
                $url = null;

                try {
                    if (filled($data['upload'] ?? null)) {
                        $url = (string) $data['upload'];
                    } elseif (filled($data['library'] ?? null)) {
                        $url = self::onlyLibraryUrls((array) $data['library'])[0] ?? null;
                    } elseif (filled($data['link'] ?? null)) {
                        $url = self::storeFromUrl((string) $data['link']);
                    }
                } catch (RuntimeException $e) {
                    Notification::make()->title('ছবি আনা যায়নি')->body($e->getMessage())->danger()->send();

                    return;
                }

                if (! $url) {
                    Notification::make()->title('কোনো ছবি বাছা হয়নি')->warning()->send();

                    return;
                }

                $component->state((string) $component->getState().'<p><img src="'.e($url).'" alt=""></p>');
            });
    }

    /** Save an upload for a field without a model; a refusal is shown to the admin instead of thrown. */
    private static function storeOrNotify($file): ?string
    {
        try {
            return self::store($file);
        } catch (Throwable $e) {
            Notification::make()->title('ছবি আপলোড হয়নি')->body($e->getMessage())->danger()->send();

            return null;
        }
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

        $stored = Storage::disk(config('media.disk'))->putFileAs(config('media.directory'), new File($path), $name);

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
            ->hintAction(self::insertImageAction())
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
