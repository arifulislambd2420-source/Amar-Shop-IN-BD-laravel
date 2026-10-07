<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Category;
use App\Models\LandingPage;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * Dynamic /sitemap.xml: home, shop, product, category, blog, policy pages and
 * active landing pages. Cached for an hour (never contains private pages).
 */
class SitemapController extends Controller
{
    private const TTL = 3600;

    public function __invoke()
    {
        $xml = Cache::remember('sitemap.xml', self::TTL, fn () => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function build(): string
    {
        $urls = [];
        $add = function (string $loc, $lastmod = null, string $freq = 'weekly', string $priority = '0.6') use (&$urls) {
            $urls[] = compact('loc', 'lastmod', 'freq', 'priority');
        };

        $add(url('/'), null, 'daily', '1.0');
        $add(route('shop'), null, 'daily', '0.9');
        $add(route('offers'), null, 'daily', '0.7');
        $add(route('brands'), null, 'monthly', '0.4');

        foreach (['about', 'delivery', 'returns', 'privacy', 'terms', 'contact'] as $page) {
            $add(route($page), null, 'monthly', '0.3');
        }

        Product::storefront()->orderBy('id')->get(['slug', 'updated_at'])
            ->each(fn ($p) => $add(route('product.show', $p->slug), $p->updated_at, 'weekly', '0.8'));

        Category::orderBy('id')->get(['slug'])
            ->each(fn ($c) => $add(route('shop', ['category' => $c->slug]), null, 'weekly', '0.6'));

        $blogs = Blog::whereNotNull('published_at')->where('published_at', '<=', now())->orderByDesc('published_at')->get(['slug', 'published_at']);

        if ($blogs->isNotEmpty()) {
            $add(route('blog.index'), $blogs->first()->published_at, 'weekly', '0.5');
            $blogs->each(fn ($b) => $add(route('blog.show', $b->slug), $b->published_at, 'monthly', '0.5'));
        }

        LandingPage::active()->orderBy('id')->get(['slug', 'updated_at'])
            ->each(fn ($l) => $add(route('landing.show', $l->slug), $l->updated_at, 'weekly', '0.5'));

        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $u) {
            $out .= '  <url><loc>'.htmlspecialchars($u['loc'], ENT_XML1).'</loc>';
            if ($u['lastmod']) {
                $out .= '<lastmod>'.$u['lastmod']->toAtomString().'</lastmod>';
            }
            $out .= "<changefreq>{$u['freq']}</changefreq><priority>{$u['priority']}</priority></url>\n";
        }

        return $out.'</urlset>'."\n";
    }
}
