<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
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

    protected function casts(): array
    {
        return [
            'read_time' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
