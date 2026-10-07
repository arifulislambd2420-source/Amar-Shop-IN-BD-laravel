<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    // Old table has only created_at (no updated_at).
    public const UPDATED_AT = null;

    protected $fillable = [
        'landing_page_id',
        'is_flagged',
        'flag_reason',
        'ip_address',
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
        'transaction_id',
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
        'courier_error',
    ];

    protected function casts(): array
    {
        return [
            'advance_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'is_flagged' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Orders that count as a real purchase attempt for fraud rules: not
     * cancelled, and not a bKash order that never got paid (an abandoned or
     * failed bKash attempt followed by a retry is normal, not a duplicate).
     */
    public function scopeCounted($query)
    {
        return $query->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->where('payment_method', '!=', 'bkash')->orWhereNotIn('payment_status', ['unpaid', 'failed']));
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }
}
