<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaLibrary extends Model
{
    protected $table = 'media_library';

    // Old table has only created_at (no updated_at).
    public const UPDATED_AT = null;

    protected $fillable = [
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
