<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashSale extends Model
{
    // Old table has only created_at (no updated_at).
    public const UPDATED_AT = null;

    protected $fillable = [
        'title',
        'end_time',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'end_time' => 'datetime',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FlashSaleItem::class);
    }
}
