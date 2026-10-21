<?php

namespace App\Support;

/**
 * Which proxies' X-Forwarded-* headers the app believes (so $request->ip(),
 * rate limits and the IP-block list see the real visitor, not the proxy).
 *
 * Trusting every caller ('*') lets any visitor fake their IP with a header, so
 * that is opt-in only. TRUSTED_PROXIES in .env:
 *   - empty (default): loopback and private networks only — right for a
 *     reverse proxy / load balancer on the same host or LAN;
 *   - "1.2.3.4,10.0.0.0/8": exactly those addresses / CIDR ranges;
 *   - "*": trust whoever connects (only if the host cannot be reached
 *     directly, e.g. managed hosting that always sits behind its own proxy
 *     and its address is unknown — see DEPLOY.md).
 */
class TrustedProxies
{
    /** Loopback + RFC 1918 private ranges. */
    public const PRIVATE_RANGES = [
        '127.0.0.1',
        '::1',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
    ];

    /** @return string|array<int, string> */
    public static function resolve(?string $configured = null): string|array
    {
        // config('proxies.trusted'), not env(): env() is empty once the config is cached (php artisan config:cache),
        // which used to silently drop TRUSTED_PROXIES on a deployed shop.
        $configured = trim((string) ($configured ?? config('proxies.trusted', '')));

        if ($configured === '*') {
            return '*';
        }

        $list = array_values(array_filter(array_map('trim', explode(',', $configured))));

        return $list !== [] ? $list : self::PRIVATE_RANGES;
    }
}
