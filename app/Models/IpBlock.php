<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpBlock extends Model
{
    // Old table has only created_at (no updated_at).
    public const UPDATED_AT = null;

    protected $fillable = [
        'ip',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
