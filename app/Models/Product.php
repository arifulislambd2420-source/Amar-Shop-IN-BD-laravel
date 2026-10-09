<?php

namespace App\Models;

use App\Models\Concerns\FlushesStorefrontCache;
use App\Support\SiteSettingsHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use FlushesStorefrontCache;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'sale_price',
        'image',
        'category_id',
        'brand_id',
        'stock',
        'is_active',
        'sku',
        'status',
        'cost_price',
        'seo_title',
        'meta_description',
        'tags',
    ];

    protected static function booted(): void
    {
        // The column is NOT NULL (default ''), but the admin form sends null
        // when no picture is chosen — that used to fail the whole save.
        static::saving(function (Product $product): void {
            $product->image ??= '';
        });
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** Only products that should ever be visible on the storefront. */
    public function scopeStorefront(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('status', 'published');
    }

    /** Stock at or below this counts as "low" (Site Setting → low_stock_threshold, default 5). */
    public static function lowStockThreshold(): int
    {
        $value = SiteSettingsHelper::get('low_stock_threshold');

        return is_numeric($value) ? max(0, (int) $value) : 5;
    }

    /** Adds approved_reviews_count / approved_reviews_avg (for star ratings on cards). */
    public function scopeWithRating(Builder $query): Builder
    {
        $approved = fn ($q) => $q->where('approved', true);

        return $query
            ->withCount(['reviews as approved_reviews_count' => $approved])
            ->withAvg(['reviews as approved_reviews_avg' => $approved], 'rating');
    }

    /** Discounted products only (sale_price set and lower than price). */
    public function scopeOnSale(Builder $query): Builder
    {
        return $query->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'price');
    }

    /**
     * Shared shop-grid query: category/brand/search/sort, used by /shop and
     * /offers so both pages behave identically.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (! empty($filters['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $filters['category']));
        }

        if (! empty($filters['brand'])) {
            $query->where('brand_id', (int) $filters['brand']);
        }

        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)->orWhere('tags', 'like', $term);
            });
        }

        return match ($filters['sort'] ?? '') {
            'price_asc' => $query->orderByRaw('COALESCE(sale_price, price) asc'),
            'price_desc' => $query->orderByRaw('COALESCE(sale_price, price) desc'),
            'newest' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };
    }

    public function displayPrice(): float
    {
        return (float) ($this->sale_price ?? $this->price);
    }

    public function hasDiscount(): bool
    {
        return $this->sale_price !== null && (float) $this->sale_price < (float) $this->price;
    }

    /**
     * Nothing left to buy: with sizes/variants, only when every variant is
     * out of stock (the product's own stock column is not reliable then);
     * without, when the product's stock is 0.
     */
    public function isSoldOut(): bool
    {
        $variants = $this->relationLoaded('variants') ? $this->variants : $this->variants()->get();

        return $variants->isNotEmpty()
            ? $variants->every(fn ($v) => $v->stock <= 0)
            : $this->stock <= 0;
    }

    public function discountPercent(): int
    {
        if (! $this->hasDiscount() || (float) $this->price <= 0) {
            return 0;
        }

        return (int) round((((float) $this->price - (float) $this->sale_price) / (float) $this->price) * 100);
    }
}
