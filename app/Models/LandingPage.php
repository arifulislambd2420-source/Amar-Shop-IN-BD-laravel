<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandingPage extends Model
{
    use \App\Models\Concerns\FlushesStorefrontCache;

    /**
     * Block-builder templates: the page is its ordered $blocks, styled by
     * the template's color scheme (resources/views/landing/templates/{key}.blade.php
     * once that design exists).
     */
    public const BLOCK_TEMPLATES = [
        'green' => 'Green',
        'purple' => 'Purple',
        'cream' => 'Cream',
    ];

    /** Default [primary, secondary] colors of each block template (overridable per page). */
    public const TEMPLATE_COLORS = [
        'green' => ['primary' => '#1f7a3d', 'secondary' => '#7fcf9b'],
        'purple' => ['primary' => '#8b1d6c', 'secondary' => '#f6b8e8'],
        'cream' => ['primary' => '#111c3a', 'secondary' => '#d9731a'],
    ];

    /** Pre-builder templates: render the fixed headline/hero/gallery/features fields. */
    public const LEGACY_TEMPLATES = [
        'template-1' => 'Template 1 — Warm/classic (পুরনো)',
        'template-2' => 'Template 2 — Dark/premium (পুরনো)',
        'template-3' => 'Template 3 — Minimal/clean (পুরনো)',
    ];

    /** resources/views/landing/templates/{key}.blade.php for each option. */
    public const TEMPLATE_OPTIONS = self::BLOCK_TEMPLATES + self::LEGACY_TEMPLATES;

    /** Block types of the page builder (see LandingPageResource::blockSchemas()). */
    public const BLOCK_TYPES = ['hero', 'features', 'checklist', 'gallery', 'reviews', 'video', 'variants', 'cta', 'notice', 'order_form'];

    protected $fillable = [
        'title',
        'slug',
        'template',
        'primary_color',
        'secondary_color',
        'product_id',
        'seo_title',
        'seo_description',
        'og_image',
        'hero_image',
        'headline',
        'sub_headline',
        'description',
        'gallery',
        'features',
        'blocks',
        'packages',
        'price_override',
        'button_text',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'features' => 'array',
            'blocks' => 'array',
            'packages' => 'array',
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

    /**
     * This page's [primary, secondary] colors: its own picks, else the
     * template's defaults. Never the site-wide brand color.
     *
     * @return array{primary: string, secondary: string}
     */
    public function colors(): array
    {
        $defaults = self::TEMPLATE_COLORS[$this->template] ?? self::TEMPLATE_COLORS['green'];
        $valid = fn (?string $c) => $c && preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtolower($c) : null;

        return [
            'primary' => $valid($this->primary_color) ?? $defaults['primary'],
            'secondary' => $valid($this->secondary_color) ?? $defaults['secondary'],
        ];
    }

    public static function isLegacyTemplate(?string $template): bool
    {
        return array_key_exists((string) $template, self::LEGACY_TEMPLATES);
    }

    /** Blocks in saved order, without the ones switched off with "লুকাও". */
    public function visibleBlocks(): array
    {
        return array_values(array_filter(
            (array) $this->blocks,
            fn ($block) => is_array($block) && ! ($block['data']['hidden'] ?? false),
        ));
    }

    /**
     * A new, inactive copy: " (Copy)" title, unique slug, zero views, no
     * orders. Content (legacy fields, blocks, packages) is copied as-is.
     */
    public function duplicate(): self
    {
        $copy = $this->replicate(['views']);
        $copy->title = $this->title.' (Copy)';
        $copy->is_active = false;
        $copy->views = 0;

        $base = $this->slug.'-copy';
        $slug = $base;
        for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }
        $copy->slug = $slug;

        $copy->save();

        return $copy;
    }

    /**
     * The view for this page's template. Unknown templates, and block
     * templates whose design view doesn't exist yet, fall back to template-1
     * so a page never 500s.
     */
    public function templateView(): string
    {
        $key = array_key_exists($this->template, self::TEMPLATE_OPTIONS) ? $this->template : 'template-1';

        return view()->exists("landing.templates.{$key}") ? "landing.templates.{$key}" : 'landing.templates.template-1';
    }
}
