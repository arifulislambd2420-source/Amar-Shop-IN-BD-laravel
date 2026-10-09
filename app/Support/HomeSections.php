<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Collection;

/**
 * Which sections the home page shows and in what order — edited by the shop
 * owner in Site Setting → "হোমপেজের সেকশন" (stored as JSON in the
 * `home_sections` setting: [{"key": "hero", "on": true}, ...]).
 *
 * Sections not in the saved list (e.g. one added by a later update) are
 * appended in their default place, switched on, so an update never makes a
 * section vanish. Unknown keys in the saved list are ignored.
 */
class HomeSections
{
    /** key => default Bangla label, in the default order. */
    public const SECTIONS = [
        'hero' => 'হিরো ব্যানার ও সাইড কার্ড',
        'categories' => 'ক্যাটাগরি',
        'flash_sale' => 'ফ্ল্যাশ সেল',
        'offers' => 'বিশেষ ছাড়',
        'showcase' => 'ক্যাটাগরি অনুযায়ী পণ্য',
        'new_products' => 'নতুন পণ্য',
        'brands' => 'ব্র্যান্ডসমূহ',
        'blog' => 'ব্লগ',
        'promo' => 'প্রোমো ব্যানার',
    ];

    /** @return list<array{key: string, label: string, on: bool}> every section, in the saved order */
    public static function all(): array
    {
        $saved = json_decode((string) SiteSettingsHelper::get('home_sections'), true);
        $saved = is_array($saved) ? $saved : [];

        $result = [];
        $seen = [];

        foreach ($saved as $row) {
            $key = is_array($row) ? ($row['key'] ?? null) : null;

            if (! is_string($key) || ! isset(self::SECTIONS[$key]) || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = ['key' => $key, 'label' => self::SECTIONS[$key], 'on' => (bool) ($row['on'] ?? true)];
        }

        foreach (self::SECTIONS as $key => $label) {
            if (! isset($seen[$key])) {
                $result[] = ['key' => $key, 'label' => $label, 'on' => true];
            }
        }

        return $result;
    }

    /** @return list<string> keys of the switched-on sections, in order */
    public static function enabled(): array
    {
        return array_values(array_map(
            fn (array $s) => $s['key'],
            array_filter(self::all(), fn (array $s) => $s['on']),
        ));
    }

    /** Ids of the categories picked for the "products by category" section (empty = automatic). */
    public static function showcaseCategoryIds(): array
    {
        $ids = json_decode((string) SiteSettingsHelper::get('home_showcase_categories'), true);

        return is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : [];
    }

    /**
     * Categories for the "products by category" section: the ones the owner
     * picked (in that order), otherwise the first two that have products.
     *
     * @return Collection<int, Category>
     */
    public static function showcaseCategories(): Collection
    {
        $ids = self::showcaseCategoryIds();

        if ($ids !== []) {
            return Category::whereIn('id', $ids)->get()->sortBy(fn (Category $c) => array_search($c->id, $ids, true))->values();
        }

        return Category::whereHas('products', fn ($q) => $q->storefront())->orderBy('id')->take(2)->get();
    }
}
