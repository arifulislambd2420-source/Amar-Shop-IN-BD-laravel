<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PageContent extends Model
{
    // Old table has only updated_at (no created_at).
    public const CREATED_AT = null;

    protected $fillable = [
        'page_key',
        'section_key',
        'element_key',
        'content_type',
        'content_value',
        'settings_json',
        'is_published',
        'version',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'settings_json' => 'array',
            'is_published' => 'boolean',
            'version' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ContentRevision::class, 'content_id');
    }
}
