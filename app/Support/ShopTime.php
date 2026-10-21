<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The shop's own clock. The database keeps every time in UTC (the app timezone — changing that would
 * shift every saved order by hours), but customers and the owner live in Bangladesh (UTC+6, no daylight
 * saving), so what they SEE — order times, invoice dates, "today's orders" — is shown in config('site.timezone')
 * (SHOP_TIMEZONE, Asia/Dhaka by default). Without this an order placed at 9 PM showed as 3 PM, and
 * "today" on the dashboard started at 6 AM.
 */
class ShopTime
{
    public static function zone(): string
    {
        return (string) config('site.timezone', 'Asia/Dhaka');
    }

    /** A stored (UTC) time as the shop's local time, for showing. */
    public static function local(?CarbonInterface $time): ?Carbon
    {
        return $time ? Carbon::instance($time)->setTimezone(self::zone()) : null;
    }

    /** Today's date (Y-m-d) in the shop's timezone. */
    public static function today(): string
    {
        return Carbon::now(self::zone())->toDateString();
    }

    /** When the shop's day, $daysAgo days back, began — as a UTC time, ready to compare with created_at. */
    public static function dayStart(int $daysAgo = 0): Carbon
    {
        return Carbon::now(self::zone())->subDays($daysAgo)->startOfDay()->utc();
    }

    /** When the shop's current month began (UTC). */
    public static function monthStart(): Carbon
    {
        return Carbon::now(self::zone())->startOfMonth()->utc();
    }
}
