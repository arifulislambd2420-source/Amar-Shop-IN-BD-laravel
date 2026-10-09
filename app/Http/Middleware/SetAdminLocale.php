<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel is in Bangla: Filament ships a `bn` translation for its own
 * buttons and messages, so switching the locale for the panel's requests (page
 * loads and Livewire updates alike) translates all of those. The storefront's
 * locale is left alone.
 */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('bn');

        return $next($request);
    }
}
