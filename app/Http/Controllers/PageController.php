<?php

namespace App\Http\Controllers;

use App\Support\SiteSettingsHelper;

/**
 * Public info pages. Each can be overridden with rich text from
 * Admin → Info Pages (site_settings `page_<key>`); when that is empty the
 * view shows its built-in text.
 */
class PageController extends Controller
{
    public function about()
    {
        return $this->page('about');
    }

    public function privacy()
    {
        return $this->page('privacy');
    }

    public function terms()
    {
        return $this->page('terms');
    }

    public function returns()
    {
        return $this->page('returns');
    }

    public function delivery()
    {
        return $this->page('delivery');
    }

    private function page(string $key)
    {
        return view('pages.'.$key, [
            'customContent' => SiteSettingsHelper::get('page_'.$key),
        ]);
    }
}
