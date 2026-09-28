<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandingPage extends Model
{
    /** resources/views/landing/templates/{key}.blade.php for each option. */
    public const TEMPLATE_OPTIONS = [
        'template-1' => 'Template 1 — Warm/classic',
        'template-2' => 'Template 2 — Dark/premium',
        'template-3' => 'Template 3 — Minimal/clean',
    ];

    protected $fillable = [
        'title',
        'slug',
        'template',
        'product_id',
        'hero_image',
        'headline',
        'sub_headline',
        'description',
        'gallery',
        'features',
        'price_override',
        'button_text',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'features' => 'array',
            'price_override' => 'decimal:2',
            'is_active' => 'boolean',
            'views' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Only pages that should ever be publicly reachable at /lp/{slug}. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The price to charge/display for this page: the page's own override if
     * set, else the linked product's normal display price. Both sources are
     * server-side data (DB), never the client.
     */
    public function effectivePrice(): float
    {
        if ($this->price_override !== null) {
            return (float) $this->price_override;
        }

        return $this->product?->displayPrice() ?? 0.0;
    }

    /** Falls back to template-1 if the stored value isn't a known template. */
    public function templateView(): string
    {
        $key = array_key_exists($this->template, self::TEMPLATE_OPTIONS) ? $this->template : 'template-1';

        return "landing.templates.{$key}";
    }
}
