<?php

namespace App\Http\Middleware;

use App\Models\IpBlock;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the admin-managed IP Blocks list (App\Filament\Resources\
 * IpBlocks\IpBlockResource / App\Models\IpBlock) — previously that
 * resource let an admin add IPs but nothing ever read the table back.
 *
 * Registered only on the app's public 'web' middleware group (see
 * bootstrap/app.php), never on the Filament admin panel — the panel has
 * its own separate middleware stack (App\Providers\Filament\
 * AdminPanelProvider::middleware()), so this is structurally impossible
 * to apply to /admin. An admin blocking their own IP by mistake can
 * still always reach the admin panel.
 */
class BlockBannedIps
{
    public function handle(Request $request, Closure $next): Response
    {
        $blockedIps = Cache::remember(
            IpBlock::CACHE_KEY,
            IpBlock::CACHE_TTL,
            fn () => IpBlock::pluck('ip')->all(),
        );

        abort_if(in_array($request->ip(), $blockedIps, true), 403);

        return $next($request);
    }
}
