<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers on every storefront and admin response.
 *
 * No Content-Security-Policy on purpose: the site loads GTM / Meta Pixel and
 * uses inline Alpine/Livewire scripts, so a CSP needs a per-site allow-list.
 * X-Frame-Options is SAMEORIGIN (not DENY) so Filament previews keep working.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self)');

        // HSTS only over HTTPS (browsers ignore it on HTTP, and sending it
        // from a plain-HTTP dev server would be misleading).
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Don't advertise the PHP version.
        $headers->remove('X-Powered-By');
        if (function_exists('header_remove')) {
            @header_remove('X-Powered-By');
        }

        return $response;
    }
}
