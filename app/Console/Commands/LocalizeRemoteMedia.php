<?php

namespace App\Console\Commands;

use App\Filament\Support\ImageUpload;
use App\Support\SiteSettingsHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Brings images that are still hosted somewhere else (old CDN links saved
 * before images moved to this server) onto this server: downloads each one,
 * checks it really is a JPG/PNG/WEBP within the size limit, stores it in
 * storage/app/public/media and rewrites the database to the new local URL.
 *
 * Safe to re-run: rewritten rows no longer match. Files that can't be
 * downloaded or fail the checks are listed and left exactly as they were.
 * Always try --dry-run first, and take a database backup before the real run.
 */
class LocalizeRemoteMedia extends Command
{
    protected $signature = 'media:localize-remote
        {--dry-run : Only list what would be downloaded}
        {--host=* : Only these hosts (e.g. --host=cdn.example.com). Default: every host except this site}';

    protected $description = 'Download externally hosted images referenced in the database onto this server and update their URLs';

    /** table => columns that can hold an image URL (directly, in JSON, or inside HTML). */
    private const TARGETS = [
        'products' => ['image', 'description'],
        'product_images' => ['url'],
        'banners' => ['image'],
        'brands' => ['logo'],
        'categories' => ['icon'],
        'blogs' => ['cover', 'content'],
        'landing_pages' => ['hero_image', 'gallery', 'description', 'blocks', 'packages', 'og_image'],
        'site_settings' => ['setting_value'],
    ];

    /** An absolute image URL; works when slashes are JSON-escaped (\/) too. */
    private const URL_PATTERN = '#https?:(?:\\\\?/){2}[^\s"\'<>()\\\\]+?\.(?:jpe?g|png|webp)(?:\?[^\s"\'<>()\\\\]*)?#i';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $hosts = array_map('strtolower', array_filter((array) $this->option('host')));
        $ownHost = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));

        $found = $this->collect($hosts, $ownHost);

        if ($found === []) {
            $this->info('কোনো বাইরের ছবির লিংক পাওয়া যায়নি। কিছু করার নেই।');

            return self::SUCCESS;
        }

        $this->info(($dry ? '[dry run] ' : '').count($found).' টি বাইরের ছবি পাওয়া গেছে।');

        $done = 0;
        $failed = [];

        foreach ($found as $url => $where) {
            if ($dry) {
                $this->line("  {$url}  ←  ".implode(', ', $where));

                continue;
            }

            try {
                $local = $this->download($url);
                $rows = $this->rewrite($url, $local);
                $this->line("  ✔ {$url} → {$local} ({$rows} জায়গা)");
                $done++;
            } catch (Throwable $e) {
                $failed[$url] = $e->getMessage();
                $this->line("  ✘ {$url}: {$e->getMessage()}");
            }
        }

        $this->newLine();

        if (! $dry) {
            $this->info("{$done} টি ছবি সার্ভারে আনা হয়েছে।");
        }

        if ($failed) {
            $this->warn(count($failed).' টি আনা যায়নি (আগের মতোই আছে):');
            $this->table(['URL', 'কারণ'], collect($failed)->map(fn ($r, $u) => [$u, $r])->values()->all());
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string, list<string>> url => ["table.column#id", …] */
    private function collect(array $hosts, string $ownHost): array
    {
        $found = [];

        foreach (self::TARGETS as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)
                    ->where($column, 'like', '%http%')
                    ->orderBy('id')
                    ->select(['id', $column])
                    ->each(function ($row) use (&$found, $table, $column, $hosts, $ownHost) {
                        preg_match_all(self::URL_PATTERN, (string) $row->{$column}, $m);

                        foreach ($m[0] as $raw) {
                            $url = str_replace('\\/', '/', $raw);
                            $host = strtolower((string) parse_url($url, PHP_URL_HOST));

                            if ($host === $ownHost || ($hosts && ! in_array($host, $hosts, true))) {
                                continue;
                            }

                            $found[$url][] = "{$table}.{$column}#{$row->id}";
                        }
                    });
            }
        }

        return array_map(fn (array $w) => array_values(array_unique($w)), $found);
    }

    private function download(string $url): string
    {
        $response = Http::timeout(25)->withHeaders(['Accept' => 'image/*'])->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('ডাউনলোড হয়নি (HTTP '.$response->status().')');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'media');

        try {
            file_put_contents($tmp, $response->body());

            return ImageUpload::storeFromPath($tmp, basename((string) parse_url($url, PHP_URL_PATH)));
        } finally {
            @unlink($tmp);
        }
    }

    /** Replace $remote with $local in every target column; returns the number of rows changed. */
    private function rewrite(string $remote, string $local): int
    {
        $escapedRemote = str_replace('/', '\\/', $remote);
        $escapedLocal = str_replace('/', '\\/', $local);
        $changed = 0;

        DB::transaction(function () use ($remote, $local, $escapedRemote, $escapedLocal, &$changed) {
            foreach (self::TARGETS as $table => $columns) {
                foreach ($columns as $column) {
                    $rows = DB::table($table)
                        ->where(fn ($q) => $q->where($column, 'like', '%'.$remote.'%')->orWhere($column, 'like', '%'.$escapedRemote.'%'))
                        ->get(['id', $column]);

                    foreach ($rows as $row) {
                        $old = (string) $row->{$column};
                        $new = str_replace([$escapedRemote, $remote], [$escapedLocal, $local], $old);

                        if ($new !== $old) {
                            DB::table($table)->where('id', $row->id)->update([$column => $new]);
                            $changed++;

                            if ($table === 'site_settings') {
                                SiteSettingsHelper::forget((string) DB::table('site_settings')->where('id', $row->id)->value('setting_key'));
                            }
                        }
                    }
                }
            }
        });

        return $changed;
    }
}
