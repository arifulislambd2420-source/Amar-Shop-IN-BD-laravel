<?php

namespace App\Support;

use App\Models\MediaLibrary;
use Illuminate\Support\Facades\Storage;

/**
 * One copy per picture.
 *
 * - On upload: the file's SHA-256 is looked up first; a picture that is already
 *   stored is reused (existingFor()) instead of being saved again.
 * - For pictures stored before that: plan() finds the identical ones and merge()
 *   keeps the oldest, points every place that used a copy at it
 *   (MediaReferences::replace) and only then deletes the copies.
 *
 * The demo pictures (demo-*.png) are never merged — `demo:remove` owns them.
 */
class MediaDedupe
{
    /** How many not-yet-hashed library rows are hashed on the fly during an upload. */
    private const LAZY_HASH_LIMIT = 300;

    public static function hash(string $path): ?string
    {
        $hash = @hash_file('sha256', $path);

        return $hash === false ? null : $hash;
    }

    /** The already-stored picture with exactly this content, if its file still exists. */
    public static function existingFor(string $hash): ?MediaLibrary
    {
        $find = fn () => MediaLibrary::where('file_hash', $hash)->orderBy('id')->get()
            ->first(fn (MediaLibrary $m) => ! self::isDemo($m->file_path) && self::fileExists($m->file_path));

        if ($found = $find()) {
            return $found;
        }

        // Older rows have no hash yet: hash a batch of them now and look again.
        return self::hashPending(self::LAZY_HASH_LIMIT) > 0 ? $find() : null;
    }

    /** Hash up to $limit library rows that have none yet; returns how many got one. */
    public static function hashPending(int $limit = self::LAZY_HASH_LIMIT): int
    {
        $done = 0;

        MediaLibrary::whereNull('file_hash')->orderBy('id')->limit($limit)->get()->each(function (MediaLibrary $m) use (&$done) {
            if (! self::fileExists($m->file_path)) {
                return;
            }

            if ($hash = self::hash(self::diskPath($m->file_path))) {
                $m->forceFill(['file_hash' => $hash])->save();
                $done++;
            }
        });

        return $done;
    }

    /**
     * Groups of identical pictures (two or more files with the same content).
     *
     * @return list<array{hash: string, keep: string, copies: list<string>, bytes: int}>
     *                                                                                   keep / copies are site URLs (/storage/media/…); bytes = space the copies take
     */
    public static function plan(): array
    {
        $disk = Storage::disk(config('media.disk'));
        $directory = config('media.directory');
        $rows = MediaLibrary::query()->get()->keyBy('file_path');
        $groups = [];

        foreach ($disk->files($directory) as $path) {
            $url = Media::urlFor($path);

            if (self::isDemo($url)) {
                continue;
            }

            if ($hash = self::hash($disk->path($path))) {
                $groups[$hash][] = [
                    'url' => $url,
                    'id' => $rows[$url]->id ?? PHP_INT_MAX, // files without a library row sort last
                    'time' => $disk->lastModified($path),
                    'size' => $disk->size($path),
                ];
            }
        }

        $plan = [];

        foreach ($groups as $hash => $files) {
            if (count($files) < 2) {
                continue;
            }

            // Keep the oldest upload (lowest library id, then oldest file).
            usort($files, fn ($a, $b) => [$a['id'], $a['time'], $a['url']] <=> [$b['id'], $b['time'], $b['url']]);
            $keep = array_shift($files);

            $plan[] = [
                'hash' => $hash,
                'keep' => $keep['url'],
                'copies' => array_column($files, 'url'),
                'bytes' => (int) array_sum(array_column($files, 'size')),
            ];
        }

        return $plan;
    }

    /**
     * Merge the planned duplicates. For each copy: every place that used it is
     * pointed at the kept picture first; the copy's file is deleted only after
     * nothing refers to it any more.
     *
     * @param  list<array{hash: string, keep: string, copies: list<string>, bytes: int}>|null  $plan  default: plan()
     * @return array{groups: int, removed: int, updated: int, bytes: int, kept_in_use: list<string>}
     */
    public static function merge(?array $plan = null): array
    {
        $plan ??= self::plan();
        $disk = Storage::disk(config('media.disk'));
        $stats = ['groups' => 0, 'removed' => 0, 'updated' => 0, 'bytes' => 0, 'kept_in_use' => []];

        foreach ($plan as $group) {
            $stats['groups']++;
            $keepPath = ltrim(substr($group['keep'], strlen('/storage/')), '/');

            // The kept picture is registered with its hash (older uploads may not have a row at all).
            $keepRow = MediaLibrary::firstOrCreate(['file_path' => $group['keep']], [
                'file_name' => basename($group['keep']),
                'mime_type' => array_search(pathinfo($group['keep'], PATHINFO_EXTENSION), config('media.mimes'), true) ?: null,
                'file_size' => $disk->exists($keepPath) ? $disk->size($keepPath) : 0,
            ]);
            $keepRow->forceFill(['file_hash' => $group['hash']])->save();

            foreach ($group['copies'] as $copyUrl) {
                $copyPath = ltrim(substr($copyUrl, strlen('/storage/')), '/');

                $stats['updated'] += MediaReferences::replace($copyUrl, $group['keep']);

                // Never delete a copy something still points at.
                if (MediaReferences::find(basename($copyUrl))) {
                    $stats['kept_in_use'][] = $copyUrl;

                    continue;
                }

                $size = $disk->exists($copyPath) ? $disk->size($copyPath) : 0;
                $disk->delete($copyPath);
                Media::unmirror($copyPath);
                MediaLibrary::where('file_path', $copyUrl)->delete();

                $stats['removed']++;
                $stats['bytes'] += $size;
            }
        }

        return $stats;
    }

    private static function isDemo(string $url): bool
    {
        return str_starts_with(basename($url), 'demo-');
    }

    private static function diskPath(string $url): string
    {
        return Storage::disk(config('media.disk'))->path(ltrim(substr($url, strlen('/storage/')), '/'));
    }

    private static function fileExists(string $url): bool
    {
        return str_starts_with($url, '/storage/')
            && Storage::disk(config('media.disk'))->exists(ltrim(substr($url, strlen('/storage/')), '/'));
    }
}
