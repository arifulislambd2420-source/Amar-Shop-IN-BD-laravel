<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Small SEO helpers shared by the layouts, sitemap and robots.txt. */
class Seo
{
    /**
     * Canonical URL of the current page: the URL without tracking/filter
     * query strings, keeping only ?page=N for paginated listings.
     */
    public static function canonical(): string
    {
        $url = url()->current();
        $page = (int) request()->query('page', 1);

        return $page > 1 ? $url.'?page='.$page : $url;
    }

    /** Plain-text meta description from HTML/long text (max ~160 chars). */
    public static function description(?string $text, int $limit = 160): string
    {
        // Drop <script>/<style> blocks first: strip_tags would keep their text.
        $text = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', (string) $text);
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text))));

        return Str::limit($text, $limit, '…');
    }

    /** JSON for a <script type="application/ld+json"> block (safe inside HTML). */
    public static function jsonLd(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    /** Organization schema from Site Setting (name, logo, phone, socials). */
    public static function organization(): array
    {
        $social = array_values(array_filter(array_map(
            fn ($n) => SiteSettingsHelper::social($n),
            ['facebook', 'youtube', 'instagram', 'tiktok'],
        )));

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => SiteSettingsHelper::siteNameEn(),
            'alternateName' => SiteSettingsHelper::siteName(),
            'url' => url('/'),
        ];

        if ($logo = SiteSettingsHelper::get('site_logo')) {
            $data['logo'] = \App\Support\Media::absolute($logo);
        }
        if ($phone = SiteSettingsHelper::phoneRaw()) {
            $data['contactPoint'] = [[
                '@type' => 'ContactPoint',
                'telephone' => $phone,
                'contactType' => 'customer service',
                'areaServed' => 'BD',
                'availableLanguage' => ['bn', 'en'],
            ]];
        }
        if ($social) {
            $data['sameAs'] = $social;
        }

        return $data;
    }
}
