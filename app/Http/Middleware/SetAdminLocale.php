<?php

namespace App\Http\Middleware;

use App\Support\SiteSettingsHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel's language is a Site Setting (English by default, or Bangla):
 * the locale is set for the panel's requests — page loads and Livewire updates
 * alike. Filament ships `en` and `bn` translations for its own buttons, our own
 * texts are looked up with __() (Bangla is the source text, lang/en.json holds
 * the English). The storefront's locale is left alone.
 */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(SiteSettingsHelper::adminLocale());

        return $next($request);
    }
}
