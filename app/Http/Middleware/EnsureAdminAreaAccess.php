<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side guard for the admin roles: a manager or order-staff admin who
 * types the URL of a page outside their area gets a 403, not just a hidden
 * menu item. Runs as persistent panel middleware, so it also covers the
 * Livewire requests made from a page (Livewire replays it for the page's path).
 *
 * The first path segment after the panel path is matched to a registered
 * resource/page slug; unmatched paths (dashboard, profile) are allowed.
 */
class EnsureAdminAreaAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        $path = trim($request->path(), '/');
        $prefix = trim((string) $panel->getPath(), '/');

        if ($prefix !== '' && str_starts_with($path, $prefix)) {
            $path = trim(substr($path, strlen($prefix)), '/');
        }

        $slug = explode('/', $path)[0] ?? '';

        if ($slug !== '') {
            foreach ([...$panel->getResources(), ...$panel->getPages()] as $class) {
                if (method_exists($class, 'adminArea') && $class::getSlug() === $slug) {
                    abort_unless(AdminAccess::allows($class::adminArea()), 403);

                    break;
                }
            }
        }

        return $next($request);
    }
}
