<?php

namespace App\Http\Middleware;

use App\Support\AutoBlock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers the IP of every signed-in admin request for 30 days, so the automatic flood
 * block never locks an admin out of the shop (even when browsing it logged out).
 */
class RememberAdminIp
{
    public function handle(Request $request, Closure $next): Response
    {
        AutoBlock::rememberAdminIp($request->ip());

        return $next($request);
    }
}
