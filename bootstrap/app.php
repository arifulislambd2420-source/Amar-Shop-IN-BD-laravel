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
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Behind a reverse proxy, $request->ip() (rate limits, IP blocks, order
        // risk checks) only sees the real visitor if the proxy's
        // X-Forwarded-* headers are trusted — but trusting everyone lets any
        // visitor fake their IP. So only private ranges are trusted by
        // default; set TRUSTED_PROXIES in .env for anything else (see
        // App\Support\TrustedProxies and DEPLOY.md).
        // Guests hitting a customer-only page (/customer/account…) go to the customer login.
        // (The admin panel has its own login and never uses this.)
        $middleware->redirectGuestsTo(fn (\Illuminate\Http\Request $request) => route('customer.login'));

        $middleware->trustProxies(at: \App\Support\TrustedProxies::resolve());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
