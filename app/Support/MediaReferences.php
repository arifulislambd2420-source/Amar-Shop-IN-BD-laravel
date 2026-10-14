<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Where a stored picture is used, and what to do with those places when the
 * picture is deleted or merged into another copy.
 *
 * Deleting a picture that is still in use does not break anything: every place
 * that held it is left empty — a product gets no image (the shop shows its
 * placeholder), a brand loses its logo, a gallery just has one picture less, a
 * banner is switched off, a picture inside a text is removed from the text.
 */
class MediaReferences
{
    /**
     * table => [column => how the column holds pictures]
     *  scalar-empty: one URL, blank = ''        scalar-null: one URL, blank = NULL
     *  html: pictures inside text (<img>)       json: lists / objects holding URLs
     *  rows: a row per picture (the row is deleted)
     *
     * Every column that can hold an uploaded picture. Keep in step with
     * LocalizeRemoteMedia::TARGETS (same columns, plus product_variants.image).
     */
    public const COLUMNS = [
        'products' => ['image' => 'scalar-empty', 'description' => 'html'],
        'product_images' => ['url' => 'rows'],
        'product_variants' => ['image' => 'scalar-null'],
        'banners' => ['image' => 'scalar-empty'],
        'brands' => ['logo' => 'scalar-null'],
        'categories' => ['icon' => 'scalar-null'],
        'blogs' => ['cover' => 'scalar-null', 'content' => 'html'],
        'landing_pages' => [
            'hero_image' => 'scalar-null', 'og_image' => 'scalar-null', 'description' => 'html',
            'gallery' => 'json', 'blocks' => 'json', 'packages' => 'json',
        ],
        'site_settings' => ['setting_value' => 'scalar-empty'],
    ];

    /** What to call a row of each table in messages, and which column names it. */
    private const LABELS = [
        'products' => ['পণ্য', 'name'],
        'product_images' => ['পণ্যের গ্যালারি', null],
        'product_variants' => ['পণ্যের রং/সাইজ', null],
        'banners' => ['ব্যানার', 'position'],
        'brands' => ['ব্র্যান্ড', 'name'],
        'categories' => ['ক্যাটাগরি', 'name'],
        'blogs' => ['ব্লগ পোস্ট', 'title'],
        'landing_pages' => ['ল্যান্ডিং পেজ', 'title'],
        'site_settings' => ['সাইট সেটিং', 'setting_key'],
    ];

    /**
     * Every place that holds this picture.
     *
     * @return list<array{table: string, column: string, id: int, label: string}>
     */
    public static function find(string $basename): array
    {
        $found = [];

        foreach (self::COLUMNS as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                $rows = DB::table($table)->where($column, 'like', '%'.$basename.'%')->get(['id', $column])->filter(fn ($r) => str_contains((string) $r->{$column}, $basename));

                foreach ($rows as $row) {
                    $found[] = ['table' => $table, 'column' => $column, 'id' => (int) $row->id, 'label' => self::describe($table, (int) $row->id)];
                }
            }
        }

        return $found;
    }

    /** The places as short readable names, once each ("Product "Shirt"", "Brand "Acme""). */
    public static function labels(string $basename): array
    {
        return array_values(array_unique(array_column(self::find($basename), 'label')));
    }

    /**
     * Take the picture out of every place that holds it (the file itself is not
     * touched here). Returns how many places were changed.
     */
    public static function blank(string $basename): int
    {
        $changed = 0;

        DB::transaction(function () use ($basename, &$changed) {
            foreach (self::COLUMNS as $table => $columns) {
                foreach ($columns as $column => $kind) {
                    $changed += self::blankColumn($table, $column, $kind, $basename);
                }
            }
        });

        StorefrontCache::flush();

        return $changed;
    }

    /**
     * Point every place that holds `$fromUrl` at `$toUrl` instead (used when
     * duplicate pictures are merged). Returns how many columns were changed.
     */
    public static function replace(string $fromUrl, string $toUrl): int
    {
        $escapedFrom = str_replace('/', '\\/', $fromUrl);
        $escapedTo = str_replace('/', '\\/', $toUrl);
        $needle = basename($fromUrl);
        $changed = 0;

        DB::transaction(function () use ($fromUrl, $toUrl, $escapedFrom, $escapedTo, $needle, &$changed) {
            foreach (self::COLUMNS as $table => $columns) {
                foreach (array_keys($columns) as $column) {
                    $rows = DB::table($table)->where($column, 'like', '%'.$needle.'%')->get(['id', $column])->filter(fn ($r) => str_contains((string) $r->{$column}, $needle));

                    foreach ($rows as $row) {
                        $old = (string) $row->{$column};
                        // The path wherever it appears (also inside a full http://… URL or JSON-escaped).
                        $new = str_replace([$escapedFrom, $fromUrl], [$escapedTo, $toUrl], $old);

                        if ($new !== $old) {
                            DB::table($table)->where('id', $row->id)->update([$column => $new]);
                            $changed++;
                        }
                    }
                }
            }
        });

        self::forgetSiteSettings();
        StorefrontCache::flush();

        return $changed;
    }

    private static function blankColumn(string $table, string $column, string $kind, string $basename): int
    {
        $rows = DB::table($table)->where($column, 'like', '%'.$basename.'%')->get(['id', $column])->filter(fn ($r) => str_contains((string) $r->{$column}, $basename));
        $changed = 0;

        foreach ($rows as $row) {
            $old = (string) $row->{$column};

            switch ($kind) {
                case 'rows':
                    DB::table($table)->where('id', $row->id)->delete();
                    $changed++;

                    break;
                case 'scalar-empty':
                case 'scalar-null':
                    $update = [$column => $kind === 'scalar-null' ? null : ''];
                    // A banner without its picture has nothing to show: switch it off.
                    if ($table === 'banners') {
                        $update['active'] = false;
                    }
                    DB::table($table)->where('id', $row->id)->update($update);
                    $changed++;

                    break;
                case 'html':
                    $new = self::stripImgTags($old, $basename);
                    if ($new !== $old) {
                        DB::table($table)->where('id', $row->id)->update([$column => $new]);
                        $changed++;
                    }

                    break;
                case 'json':
                    $data = json_decode($old, true);
                    if (is_array($data)) {
                        $new = json_encode(self::stripFromJson($data, $basename));
                        if ($new !== $old) {
                            DB::table($table)->where('id', $row->id)->update([$column => $new]);
                            $changed++;
                        }
                    }

                    break;
            }
        }

        if ($table === 'site_settings' && $changed) {
            self::forgetSiteSettings();
        }

        return $changed;
    }

    /** `<img …name…>` removed from a piece of HTML (an empty paragraph left behind is tidied too). */
    private static function stripImgTags(string $html, string $basename): string
    {
        $quoted = preg_quote($basename, '#');
        $html = preg_replace('#<img\b[^>]*'.$quoted.'[^>]*>#i', '', $html) ?? $html;

        return preg_replace('#<p>\s*</p>#i', '', $html) ?? $html;
    }

    /** A decoded JSON value with the picture taken out: list entries removed, single values emptied. */
    private static function stripFromJson(mixed $value, string $basename): mixed
    {
        if (is_string($value)) {
            if (! str_contains($value, $basename)) {
                return $value;
            }

            // Text holding an <img> (a block's description): drop just the tag; a plain URL becomes empty.
            return str_contains($value, '<img') ? self::stripImgTags($value, $basename) : null;
        }

        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        $out = [];

        foreach ($value as $key => $item) {
            $clean = self::stripFromJson($item, $basename);

            if ($isList && $clean === null && is_string($item)) {
                continue; // a gallery just has one picture less
            }

            $out[$key] = $clean;
        }

        return $isList ? array_values($out) : $out;
    }

    private static function describe(string $table, int $id): string
    {
        [$kind, $nameColumn] = self::LABELS[$table];

        if ($table === 'product_images') {
            $name = DB::table('product_images')->join('products', 'products.id', '=', 'product_images.product_id')->where('product_images.id', $id)->value('products.name');
        } elseif ($table === 'product_variants') {
            $row = DB::table('product_variants')->join('products', 'products.id', '=', 'product_variants.product_id')->where('product_variants.id', $id)->first(['products.name as product', 'product_variants.label as label']);
            $name = $row ? $row->product.' — '.$row->label : null;
        } else {
            $name = DB::table($table)->where('id', $id)->value($nameColumn);
        }

        $kind = Str::singular(__($kind)); // "Products" -> "Product" in English, unchanged in Bangla

        return $kind.($name !== null && $name !== '' ? ' "'.$name.'"' : ' #'.$id);
    }

    private static function forgetSiteSettings(): void
    {
        foreach (DB::table('site_settings')->pluck('setting_key') as $key) {
            SiteSettingsHelper::forget((string) $key);
        }
    }
}
