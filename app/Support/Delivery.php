<?php

namespace App\Support;

/**
 * Delivery charge rules, editable in Site Setting → Delivery:
 *  - a charge for Dhaka and a charge for everywhere else, picked by the
 *    order's district,
 *  - an optional minimum order amount (subtotal, before coupon discount) at
 *    or above which delivery is free; empty = no free delivery.
 * Empty charge fields fall back to config('site.delivery').
 *
 * OrderService is the only place the charge is *applied* to an order; the
 * storefront uses quote()/the fee getters just to display it.
 */
class Delivery
{
    public const DHAKA = 'ঢাকা';

    public static function dhakaFee(): float
    {
        return self::money('delivery_fee_dhaka', (float) config('site.delivery.dhaka'));
    }

    public static function outsideFee(): float
    {
        return self::money('delivery_fee_outside', (float) config('site.delivery.outside'));
    }

    /** Minimum subtotal for free delivery, or null when free delivery is off. */
    public static function freeMin(): ?float
    {
        $value = SiteSettingsHelper::get('delivery_free_min');

        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    public static function isDhaka(?string $district): bool
    {
        $district = mb_strtolower(trim((string) $district));

        return $district === mb_strtolower(self::DHAKA) || $district === 'dhaka';
    }

    /** True when Dhaka and outside-Dhaka cost the same (so the district doesn't matter). */
    public static function isUniform(): bool
    {
        return self::dhakaFee() === self::outsideFee();
    }

    public static function qualifiesForFree(float $subtotal): bool
    {
        $min = self::freeMin();

        return $min !== null && $subtotal >= $min;
    }

    /** How much more the customer must add to the cart to get free delivery (null = n/a or already free). */
    public static function remainingForFree(float $subtotal): ?float
    {
        $min = self::freeMin();

        return $min !== null && $subtotal < $min ? $min - $subtotal : null;
    }

    /** The charge that is applied to an order. An unknown district is charged the outside-Dhaka rate. */
    public static function fee(?string $district, float $subtotal): float
    {
        if (self::qualifiesForFree($subtotal)) {
            return 0.0;
        }

        return self::isDhaka($district) ? self::dhakaFee() : self::outsideFee();
    }

    /** Like fee(), but null when it can't be known yet (no district chosen and the two rates differ). */
    public static function quote(?string $district, float $subtotal): ?float
    {
        if (self::qualifiesForFree($subtotal) || self::isUniform()) {
            return self::fee($district, $subtotal);
        }

        return filled($district) ? self::fee($district, $subtotal) : null;
    }

    private static function money(string $key, float $default): float
    {
        $value = SiteSettingsHelper::get($key);

        return is_numeric($value) && (float) $value >= 0 ? (float) $value : $default;
    }
}
