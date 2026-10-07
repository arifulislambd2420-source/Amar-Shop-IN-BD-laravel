<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    use \App\Models\Concerns\FlushesStorefrontCache;

    // Old table has neither created_at nor updated_at (only published_at).
    public $timestamps = false;

    protected $fillable = [
        'title',
        'slug',
        'cover',
        'category',
        'content',
        'read_time',
        'published_at',
    ];

    /** Published posts exist? Cached; cleared whenever a post is saved or deleted. */
    public static function hasPublished(): bool
    {
        return \Illuminate\Support\Facades\Cache::remember('nav.has_blog', 600, fn () => static::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->exists());
    }

    protected static function booted(): void
    {
        $forget = fn () => \Illuminate\Support\Facades\Cache::forget('nav.has_blog');

        static::saved($forget);
        static::deleted($forget);
    }

    protected function casts(): array
    {
        return [
            'read_time' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
