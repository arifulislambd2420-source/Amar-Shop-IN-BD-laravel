<?php

namespace App\Http\Controllers;

use App\Models\LandingPage;

class LandingPageController extends Controller
{
    /**
     * Public landing page. Only ever serves active pages — an inactive or
     * unknown slug is a plain 404, never a hint that the slug exists.
     */
    public function show(string $slug)
    {
        $landingPage = LandingPage::active()
            ->with('product')
            ->where('slug', $slug)
            ->first();

        abort_if(! $landingPage, 404);

        // Atomic increment — safe under concurrent hits, no read-then-write race.
        $landingPage->increment('views');

        return view($landingPage->templateView(), compact('landingPage'));
    }
}
