<?php

namespace App\Console\Commands;

use App\Support\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Makes /storage/... URLs work on hosts where `php artisan storage:link`
 * fails (symlink() disabled — common on shared hosting).
 *
 *  1. Tries the normal symlink public/storage → storage/app/public.
 *  2. If that is impossible, turns public/storage into a real folder and copies
 *     every uploaded file into it (new uploads are copied automatically by
 *     the admin upload code), so the web server serves them directly.
 *
 * Safe to re-run; use it again after restoring files into storage.
 */
class LinkMedia extends Command
{
    protected $signature = 'media:link {--copy : Skip the symlink attempt and just copy the files}';

    protected $description = 'Make uploaded images reachable at /storage (symlink, or copy when symlinks are blocked)';

    public function handle(): int
    {
        $link = config('media.public_path');
        $target = storage_path('app/public');

        if (is_link($link)) {
            $this->info('public/storage is already a symlink — nothing to do.');

            return self::SUCCESS;
        }

        if (! $this->option('copy') && ! file_exists($link)) {
            try {
                if (@symlink($target, $link)) {
                    $this->info('Symlink created: public/storage → storage/app/public');

                    return self::SUCCESS;
                }
            } catch (Throwable) {
                // fall through to copying
            }

            $this->warn('Symlinks are not allowed here — copying files into public/storage instead.');
        }

        $disk = Storage::disk(config('media.disk'));
        $copied = 0;
        $failed = 0;

        foreach ($disk->allFiles() as $path) {
            // Don't publish private/non-media leftovers: only the media directory.
            if (! str_starts_with($path, config('media.directory').'/') || str_starts_with(basename($path), '.')) {
                continue;
            }

            Media::mirror($path) ? $copied++ : $failed++;
        }

        $this->info("{$copied} টি ফাইল public/storage-এ কপি হয়েছে.".($failed ? " {$failed} টি হয়নি (permission দেখুন)।" : ''));
        $this->line('এখন থেকে নতুন আপলোডও নিজে থেকে কপি হবে।');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
