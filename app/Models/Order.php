<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    // Old table has only created_at (no updated_at).
    public const UPDATED_AT = null;

    protected $fillable = [
        'order_token',
        'invoice_no',
        'customer_name',
        'phone',
        'email',
        'district',
        'thana',
        'postcode',
        'address',
        'payment_method',
        'payment_status',
        'advance_amount',
        'status',
        'subtotal',
        'shipping_fee',
        'discount',
        'total',
        'notes',
        'consignment_id',
        'tracking_code',
        'courier_status',
    ];

    protected function casts(): array
    {
        return [
            'advance_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
