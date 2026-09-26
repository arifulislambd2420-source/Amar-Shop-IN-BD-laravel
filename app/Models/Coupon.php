<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    // Old table has only created_at (no updated_at).
    public const UPDATED_AT = null;

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'min_spend',
        'uses',
        'max_uses',
        'valid_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_spend' => 'decimal:2',
            'uses' => 'integer',
            'max_uses' => 'integer',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
