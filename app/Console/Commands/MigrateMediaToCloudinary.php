<?php

namespace App\Console\Commands;

use App\Models\MediaLibrary;
use App\Services\CloudinaryService;
use App\Support\SiteSettingsHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Moves images that were uploaded to the local `public` disk
 * (storage/app/public/media, referenced as <host>/storage/media/<file> or
 * /storage/media/<file>) to Cloudinary and rewrites every database column
 * that points at them to the new Cloudinary URL.
 *
 * Safe to re-run: rows already rewritten no longer match, and each file is
 * uploaded and its rows updated together, so a failure part-way leaves the
 * remaining files for the next run. Local files are kept unless
 * --delete-local is given. Files that are referenced in the database but do
 * not exist on disk are listed (and left untouched).
 */
class MigrateMediaToCloudinary extends Command
{
    protected $signature = 'media:migrate-to-cloudinary
        {--dry-run : Only report what would be uploaded and updated}
        {--delete-local : Delete each local file after its rows were updated}';

    protected $description = 'Upload legacy local images to Cloudinary and update their URLs in the database';

    /** table => columns that can hold an uploaded image URL (directly, in JSON, or inside HTML). */
    private const TARGETS = [
        'products' => ['image'],
        'product_images' => ['url'],
        'banners' => ['image'],
        'brands' => ['logo'],
        'categories' => ['icon'],
        'blogs' => ['cover', 'content'],
        'landing_pages' => ['hero_image', 'gallery', 'description'],
        'site_settings' => ['setting_value'],
    ];

    /** <host>/storage/media/<file>, also when the slashes are JSON-escaped (\/). */
    private const URL_PATTERN = '#(?:https?:\\\\?/\\\\?/[^\s"\'<>()\\\\]+?)?(\\\\?/)storage\\\\?/media\\\\?/([A-Za-z0-9][A-Za-z0-9._-]*)#';

    public function handle(CloudinaryService $cloudinary): int
    {
        $dry = (bool) $this->option('dry-run');

        if (! $dry && ! $cloudinary->configured()) {
            $this->error('Cloudinary is not configured (CLOUDINARY_CLOUD_NAME / CLOUDINARY_API_KEY / CLOUDINARY_API_SECRET).');

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $refs = $this->collectReferences();

        if ($refs === []) {
            $this->info('No local image references found in the database. Nothing to do.');
            $this->reportOrphans($refs);

            return self::SUCCESS;
        }

        $this->info(($dry ? '[dry run] ' : '').count($refs).' local image(s) referenced in the database.');

        $migrated = 0;
        $missing = [];
        $failed = [];

        foreach ($refs as $filename => $where) {
            $path = 'media/'.$filename;

            if (! $disk->exists($path)) {
                $missing[$filename] = $where;

                continue;
            }

            if ($dry) {
                $this->line("  would upload {$filename} → ".count($where).' reference(s)');
                $migrated++;

                continue;
            }

            try {
                $url = $cloudinary->upload($disk->path($path));

                $rows = $this->rewriteReferences($filename, $url);

                MediaLibrary::firstOrCreate(['file_path' => $url], [
                    'file_name' => $filename,
                    'mime_type' => $disk->mimeType($path) ?: null,
                    'file_size' => $disk->size($path),
                ]);

                if ($this->option('delete-local')) {
                    $disk->delete($path);
                }

                $this->line("  ✔ {$filename} → {$url} ({$rows} row(s))");
                $migrated++;
            } catch (Throwable $e) {
                $failed[$filename] = $e->getMessage();
                $this->line("  ✘ {$filename}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info(($dry ? 'Would migrate' : 'Migrated')." {$migrated} file(s).");

        if ($failed) {
            $this->warn(count($failed).' upload(s) failed — re-run the command to retry them.');
        }

        if ($missing) {
            $this->newLine();
            $this->warn(count($missing).' referenced file(s) are missing on disk (left unchanged):');
            $this->table(
                ['File', 'Referenced by'],
                collect($missing)->map(fn (array $where, string $file) => [$file, implode(', ', $where)])->values()->all(),
            );
        }

        $this->reportOrphans($refs);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * filename => ["products.image#12", ...] for every row that still points at a local file.
     *
     * @return array<string, list<string>>
     */
    private function collectReferences(): array
    {
        $refs = [];

        foreach (self::TARGETS as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)
                    ->where($column, 'like', '%storage%media%')
                    ->orderBy('id')
                    ->select(['id', $column])
                    ->each(function ($row) use (&$refs, $table, $column) {
                        preg_match_all(self::URL_PATTERN, (string) $row->{$column}, $matches, PREG_SET_ORDER);

                        foreach ($matches as $m) {
                            $refs[$m[2]][] = "{$table}.{$column}#{$row->id}";
                        }
                    });
            }
        }

        return array_map(fn (array $where) => array_values(array_unique($where)), $refs);
    }

    /** Replace every local URL for $filename with $url in all target columns; returns rows updated. */
    private function rewriteReferences(string $filename, string $url): int
    {
        $updated = 0;

        DB::transaction(function () use ($filename, $url, &$updated) {
            foreach (self::TARGETS as $table => $columns) {
                foreach ($columns as $column) {
                    $rows = DB::table($table)->where($column, 'like', '%'.$filename.'%')->get(['id', $column]);

                    foreach ($rows as $row) {
                        $old = (string) $row->{$column};

                        $new = preg_replace_callback(self::URL_PATTERN, function (array $m) use ($filename, $url) {
                            if ($m[2] !== $filename) {
                                return $m[0];
                            }

                            // Keep JSON-escaped columns valid JSON.
                            return $m[1] === '\\/' ? str_replace('/', '\\/', $url) : $url;
                        }, $old);

                        if ($new !== $old) {
                            DB::table($table)->where('id', $row->id)->update([$column => $new]);
                            $updated++;

                            if ($table === 'site_settings') {
                                $key = DB::table('site_settings')->where('id', $row->id)->value('setting_key');
                                SiteSettingsHelper::forget((string) $key);
                            }
                        }
                    }
                }
            }
        });

        return $updated;
    }

    /** Tell the admin how many local files nothing references (so they can clean up). */
    private function reportOrphans(array $refs): void
    {
        $disk = Storage::disk('public');

        if (! $disk->exists('media')) {
            return;
        }

        $orphans = collect($disk->files('media'))
            ->map(fn (string $p) => basename($p))
            ->reject(fn (string $f) => isset($refs[$f]));

        if ($orphans->isNotEmpty()) {
            $this->newLine();
            $this->line("{$orphans->count()} local file(s) in storage/app/public/media are not referenced by any database column (left untouched).");
        }
    }
}
