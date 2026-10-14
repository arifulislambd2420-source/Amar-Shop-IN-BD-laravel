<?php

namespace App\Support;

use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * The shop's filters (used by /shop, /offers and search): several categories,
 * brands, colours and sizes at once (checkboxes), a price range and "in stock".
 *
 * The URL stays shareable: ?category[]=a&category[]=b&color[]=Red&price_min=500&in_stock=1.
 * The old single-value links (?category=slug, ?brand=3) still work.
 */
class ShopFilters
{
    /** Filters that hold a list of values. */
    public const LISTS = ['category', 'brand', 'color', 'size'];

    /** Sizes in the order shoppers expect them; anything else sorts after, alphabetically. */
    private const SIZE_ORDER = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', 'XXXL', '3XL', '4XL', '5XL', 'FREE', 'FREE SIZE'];

    /** Variant labels that are a size (for products made before variants had a size field). */
    private const SIZE_LABEL = '/^(X{0,3}S|M|X{0,4}L|\d{1,3})$/i';

    /**
     * What the request asks for, cleaned up.
     *
     * @return array{category: list<string>, brand: list<string>, color: list<string>, size: list<string>, price_min: ?int, price_max: ?int, in_stock: bool}
     */
    public static function fromRequest(Request $request): array
    {
        $list = fn (string $key): array => array_values(array_unique(array_filter(
            array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', Arr::wrap($request->query($key))),
            fn ($v) => $v !== '' && mb_strlen($v) <= 100,
        )));

        $money = function (string $key) use ($request): ?int {
            $value = $request->query($key);

            return is_scalar($value) && is_numeric($value) && $value >= 0 ? (int) $value : null;
        };

        $filters = [
            'category' => $list('category'),
            'brand' => array_values(array_filter($list('brand'), 'ctype_digit')),
            'color' => $list('color'),
            'size' => $list('size'),
            'price_min' => $money('price_min'),
            'price_max' => $money('price_max'),
            'in_stock' => $request->boolean('in_stock'),
        ];

        // A reversed range (500–100) is read as 100–500.
        if ($filters['price_min'] !== null && $filters['price_max'] !== null && $filters['price_min'] > $filters['price_max']) {
            [$filters['price_min'], $filters['price_max']] = [$filters['price_max'], $filters['price_min']];
        }

        return $filters;
    }

    /** How many filters are on (every ticked box, the price range and "in stock" count). */
    public static function count(array $filters): int
    {
        return count($filters['category']) + count($filters['brand']) + count($filters['color']) + count($filters['size'])
            + ($filters['price_min'] !== null || $filters['price_max'] !== null ? 1 : 0)
            + ($filters['in_stock'] ? 1 : 0);
    }

    /**
     * Apply the filters to a product query.
     *
     * Colour, size and "in stock" are checked on the same variant, so Red + M
     * only matches a product that really has a red M (not a red L and a blue M).
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        if ($filters['category']) {
            $query->whereHas('category', fn ($q) => $q->whereIn('slug', $filters['category']));
        }

        if ($filters['brand']) {
            $query->whereIn('brand_id', array_map('intval', $filters['brand']));
        }

        if ($filters['price_min'] !== null) {
            $query->whereRaw('COALESCE(sale_price, price) >= ?', [$filters['price_min']]);
        }

        if ($filters['price_max'] !== null) {
            $query->whereRaw('COALESCE(sale_price, price) <= ?', [$filters['price_max']]);
        }

        if ($filters['color'] || $filters['size']) {
            $query->whereHas('variants', function ($v) use ($filters) {
                if ($filters['color']) {
                    $v->whereIn('color', $filters['color']);
                }

                if ($filters['size']) {
                    // The size field, or the label of an older size-only variant.
                    $v->where(fn ($s) => $s->whereIn('size', $filters['size'])
                        ->orWhere(fn ($old) => $old->whereNull('size')->whereIn('label', $filters['size'])));
                }

                if ($filters['in_stock']) {
                    $v->where('stock', '>', 0);
                }
            });
        } elseif ($filters['in_stock']) {
            // Something to buy: a variant in stock, or (no variants) the product itself.
            $query->where(fn ($q) => $q
                ->whereHas('variants', fn ($v) => $v->where('stock', '>', 0))
                ->orWhere(fn ($p) => $p->whereDoesntHave('variants')->where('stock', '>', 0)));
        }

        return $query;
    }

    /**
     * The choices the filter panel offers for a listing: only colours / sizes
     * that exist on the products being listed, and the price range they span.
     *
     * @return array{colors: Collection<int, object{color: string, hex: ?string}>, sizes: list<string>, price: array{min: int, max: int}}
     */
    public static function options(Builder $listing): array
    {
        $ids = (clone $listing)->reorder()->select('products.id');
        $variants = ProductVariant::query()->whereIn('product_id', $ids);

        $colors = (clone $variants)->whereNotNull('color')->where('color', '!=', '')
            ->selectRaw('color, MIN(color_hex) as hex')->groupBy('color')->orderBy('color')->get();

        $sizes = (clone $variants)->whereNotNull('size')->where('size', '!=', '')->distinct()->pluck('size')->all();
        $oldLabels = (clone $variants)->whereNull('size')->whereNull('color')->distinct()->pluck('label')
            ->filter(fn ($label) => preg_match(self::SIZE_LABEL, trim((string) $label)))->map(fn ($l) => trim((string) $l))->all();

        $bounds = (clone $listing)->reorder()
            ->selectRaw('MIN(COALESCE(sale_price, price)) as low, MAX(COALESCE(sale_price, price)) as high')->first();

        return [
            'colors' => $colors,
            'sizes' => self::sortSizes(array_values(array_unique(array_merge($sizes, $oldLabels)))),
            'price' => ['min' => (int) floor((float) ($bounds->low ?? 0)), 'max' => (int) ceil((float) ($bounds->high ?? 0))],
        ];
    }

    /** @param  list<string>  $sizes */
    private static function sortSizes(array $sizes): array
    {
        $rank = function (string $size): array {
            $upper = mb_strtoupper(trim($size));

            if (($i = array_search($upper, self::SIZE_ORDER, true)) !== false) {
                return [0, $i, $upper];
            }

            return is_numeric($upper) ? [1, (float) $upper, $upper] : [2, 0, $upper];
        };

        usort($sizes, fn ($a, $b) => $rank($a) <=> $rank($b));

        return $sizes;
    }
}
