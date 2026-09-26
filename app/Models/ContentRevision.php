<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentRevision extends Model
{
    // Old table has only created_at (no updated_at).
    public const UPDATED_AT = null;

    protected $fillable = [
        'content_id',
        'content_value',
        'settings_json',
        'version',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'settings_json' => 'array',
            'version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function pageContent(): BelongsTo
    {
        return $this->belongsTo(PageContent::class, 'content_id');
    }
}
