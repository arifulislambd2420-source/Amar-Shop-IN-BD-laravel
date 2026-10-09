<?php

namespace App\Models;

use App\Support\Money;
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

    /** Active coupon by code, ignoring upper/lower case and spaces around it. */
    public static function findByCode(string $code, bool $lock = false): ?self
    {
        $code = trim($code);

        if ($code === '') {
            return null;
        }

        return static::query()
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])
            ->where('is_active', true)
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->first();
    }

    /** Why this coupon can't be used on an order of $subtotal (Bangla), or null when it can. */
    public function problemFor(float $subtotal): ?string
    {
        if ($this->valid_until && $this->valid_until->isPast()) {
            return 'এই কুপনের মেয়াদ শেষ হয়ে গেছে।';
        }

        if ($this->max_uses !== null && $this->uses >= $this->max_uses) {
            return 'এই কুপনটি আর ব্যবহার করা যাবে না।';
        }

        if ($subtotal < (float) $this->min_spend) {
            return 'এই কুপন ব্যবহার করতে কমপক্ষে '.Money::taka((float) $this->min_spend).' এর পণ্য কিনতে হবে।';
        }

        return null;
    }

    /** Discount in taka on an order of $subtotal (never more than the subtotal). */
    public function discountFor(float $subtotal): float
    {
        $discount = $this->discount_type === 'percent'
            ? $subtotal * min(100, (float) $this->discount_value) / 100
            : (float) $this->discount_value;

        return round(min(max($discount, 0), $subtotal), 2);
    }
}
