<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    // Old table has only created_at (no updated_at).
    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'customer_name',
        'rating',
        'comment',
        'approved',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'approved' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
