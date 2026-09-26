<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'flash_sale_id',
        'product_id',
        'flash_price',
    ];

    protected function casts(): array
    {
        return [
            'flash_price' => 'decimal:2',
        ];
    }

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
