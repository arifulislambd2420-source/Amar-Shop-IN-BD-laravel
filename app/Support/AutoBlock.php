<?php

namespace App\Support;

use App\Models\IpBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Automatic blocking of floods: an IP that sends far more requests than any visitor
 * does within a short time is blocked for a while (an IpBlock row with is_auto and an
 * expiry, so it shows up in Settings → IP Block). The limits are set there too.
 *
 * Never blocked: a signed-in admin, an IP an admin used for the panel in the last 30
 * days, and private / reserved addresses (behind a mis-set proxy every visitor would
 * look like one address — blocking it would block the whole shop).
 */
class AutoBlock
{
    public const DEFAULT_MAX_REQUESTS = 150;

    public const DEFAULT_WINDOW_SECONDS = 60;

    public const DEFAULT_BLOCK_MINUTES = 30;

    /** How long an IP that an admin used stays protected. */
    public const ADMIN_IP_DAYS = 30;

    public static function enabled(): bool
    {
        $value = SiteSettingsHelper::get('autoblock_enabled');

        return $value === null || $value === '' ? true : (bool) (int) $value;
    }

    public static function maxRequests(): int
    {
        return self::number('autoblock_max_requests', self::DEFAULT_MAX_REQUESTS, 10, 100000);
    }

    public static function windowSeconds(): int
    {
        return self::number('autoblock_window_seconds', self::DEFAULT_WINDOW_SECONDS, 5, 3600);
    }

    public static function blockMinutes(): int
    {
        return self::number('autoblock_block_minutes', self::DEFAULT_BLOCK_MINUTES, 1, 43200);
    }

    private static function number(string $key, int $default, int $min, int $max): int
    {
        $value = SiteSettingsHelper::get($key);

        return is_numeric($value) ? max($min, min($max, (int) $value)) : $default;
    }

    public static function rememberAdminIp(?string $ip): void
    {
        if ($ip) {
            Cache::put('admin_ip:'.$ip, 1, now()->addDays(self::ADMIN_IP_DAYS));
        }
    }

    /** An IP that an admin used for the panel recently. */
    public static function isAdminIp(string $ip): bool
    {
        return $ip !== '' && Cache::has('admin_ip:'.$ip);
    }

    /** Requests that are never counted or blocked. */
    public static function isExempt(Request $request): bool
    {
        $ip = $request->ip();

        return ! $ip
            // Uploaded pictures through the fallback route: one page can pull dozens.
            || $request->routeIs('media.file')
            || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            || auth('admin')->check()
            || Cache::has('admin_ip:'.$ip);
    }

    /**
     * Count this request. Returns true when the IP went over the limit just now (and is now blocked).
     */
    public static function hit(Request $request): bool
    {
        if (! self::enabled() || self::isExempt($request)) {
            return false;
        }

        $ip = (string) $request->ip();
        $key = 'flood:'.$ip;
        $window = self::windowSeconds();

        Cache::add($key, 0, $window);
        $count = (int) Cache::increment($key);

        if ($count <= self::maxRequests()) {
            return false;
        }

        Cache::forget($key);
        self::block($ip, $count, $window);

        return true;
    }

    private static function block(string $ip, int $count, int $window): void
    {
        $existing = IpBlock::where('ip', $ip)->first();

        // A permanent manual block stays as it is.
        if ($existing && ! $existing->is_auto && $existing->expires_at === null) {
            return;
        }

        IpBlock::updateOrCreate(['ip' => $ip], [
            'reason' => "Auto: {$count} requests in {$window}s",
            'is_auto' => true,
            'hits' => $count,
            'expires_at' => now()->addMinutes(self::blockMinutes()),
        ]);
    }
}
