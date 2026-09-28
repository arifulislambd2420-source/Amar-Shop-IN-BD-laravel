<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Public storefront/checkout/track/landing-page routes only — never
        // /admin, which has its own separate middleware stack (see
        // App\Providers\Filament\AdminPanelProvider::middleware()) and so is
        // structurally unaffected by this.
        $middleware->web(append: [
            \App\Http\Middleware\BlockBannedIps::class,
        ]);

        // Hostinger serves this app through a reverse proxy (its web
        // server/CDN in front of PHP), so without this, $request->ip() (and
        // therefore the IP-block check above, plus rate limiters) would see
        // the proxy's IP instead of the real visitor's. `at: '*'` trusts
        // whatever immediate hop forwards the request — standard for
        // shared/managed hosting where the exact proxy IP isn't known —
        // and reads it from the standard X-Forwarded-* headers.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
