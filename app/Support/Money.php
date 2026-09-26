<?php

namespace App\Support;

/**
 * Ported from the old Next.js app's src/lib/format.ts (formatTaka +
 * SHIPPING_FEE). Actual money formatting with locale-aware grouping needs
 * ext-intl (number_format below is a safe ext-intl-free approximation used
 * for local dev without it; verify the final look on the Hostinger server
 * which has intl installed).
 */
class Money
{
    public static function taka(float|int|string|null $amount): string
    {
        $amount = (float) ($amount ?? 0);

        return '৳'.number_format($amount, $amount == floor($amount) ? 0 : 2);
    }
}
