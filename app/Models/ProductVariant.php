<?php

namespace App\Models;

use App\Support\Stock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    public $timestamps = false;

    /** Keep the product's own stock equal to the sum of its variants (see App\Support\Stock). */
    protected static function booted(): void
    {
        $sync = fn (ProductVariant $variant) => Stock::syncProduct($variant->product_id);

        // "Red / M": the one name carts, orders and SMS show, composed from colour + size.
        static::saving(function (ProductVariant $variant) {
            $parts = array_filter([trim((string) $variant->color), trim((string) $variant->size)], fn ($p) => $p !== '');

            if ($parts) {
                $variant->label = implode(' / ', $parts);
            }
        });

        static::saved($sync);
        static::deleted($sync);
    }

    protected $fillable = [
        'product_id',
        'label',
        'color',
        'color_hex',
        'size',
        'image',
        'price',
        'stock',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
