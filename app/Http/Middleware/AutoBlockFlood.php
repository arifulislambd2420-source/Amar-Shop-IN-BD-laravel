<?php

namespace App\Http\Middleware;

use App\Support\AutoBlock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts storefront requests per IP and blocks a flood for a while (see App\Support\AutoBlock).
 * On the public 'web' group only — like BlockBannedIps, it can never reach the admin panel.
 */
class AutoBlockFlood
{
    public function handle(Request $request, Closure $next): Response
    {
        if (AutoBlock::hit($request)) {
            abort(429, 'Too many requests.', ['Retry-After' => AutoBlock::blockMinutes() * 60]);
        }

        return $next($request);
    }
}
