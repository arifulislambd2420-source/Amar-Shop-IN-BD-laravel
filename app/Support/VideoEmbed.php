<?php

namespace App\Support;

/**
 * Turns an admin-pasted video link into something safe to embed: a YouTube,
 * Facebook or Vimeo player, or a direct .mp4/.webm file. Anything else (or a
 * non-http(s) URL, or a look-alike host such as facebook.com.evil.example)
 * returns null and the page simply shows no video.
 *
 * Nothing is loaded from these hosts until the visitor taps play (see
 * landing/blocks/_video-player): the page only carries the small result below.
 */
class VideoEmbed
{
    /**
     * src = the player address, play = the same with autoplay (used once the visitor taps play),
     * thumb = a cover picture when the host gives one for free, watch = the original link.
     *
     * @return array{type: 'iframe'|'video', provider: 'youtube'|'facebook'|'vimeo'|'file', src: string, play: string, thumb: ?string, watch: string}|null
     */
    public static function parse(?string $url): ?array
    {
        $url = trim((string) $url);

        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '') {
            return null;
        }

        if ($id = self::youtubeId($host, $parts, $url)) {
            $src = "https://www.youtube.com/embed/{$id}?rel=0";

            return [
                'type' => 'iframe', 'provider' => 'youtube', 'src' => $src, 'play' => $src.'&autoplay=1&playsinline=1',
                'thumb' => "https://i.ytimg.com/vi/{$id}/hqdefault.jpg", 'watch' => $url,
            ];
        }

        if (self::isHost($host, ['facebook.com', 'fb.watch']) && self::looksLikeFacebookVideo($host, $parts)) {
            // Facebook's own player plugin; it needs the original (https) link as `href`.
            $href = rawurlencode(preg_replace('#^http://#i', 'https://', $url));
            $src = "https://www.facebook.com/plugins/video.php?href={$href}&show_text=false";

            return ['type' => 'iframe', 'provider' => 'facebook', 'src' => $src, 'play' => $src.'&autoplay=true', 'thumb' => null, 'watch' => $url];
        }

        if (self::isHost($host, ['vimeo.com']) && preg_match('#^/(?:video/)?(\d+)#', (string) ($parts['path'] ?? ''), $m)) {
            $src = "https://player.vimeo.com/video/{$m[1]}";

            return ['type' => 'iframe', 'provider' => 'vimeo', 'src' => $src, 'play' => $src.'?autoplay=1', 'thumb' => null, 'watch' => $url];
        }

        if (preg_match('#\.(mp4|webm)$#i', (string) ($parts['path'] ?? ''))) {
            return ['type' => 'video', 'provider' => 'file', 'src' => $url, 'play' => $url, 'thumb' => null, 'watch' => $url];
        }

        return null;
    }

    /** Host is the domain itself or one of its subdomains (www., m., web. …) — never a look-alike. */
    private static function isHost(string $host, array $domains): bool
    {
        foreach ($domains as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    private static function youtubeId(string $host, array $parts, string $url): ?string
    {
        $path = (string) ($parts['path'] ?? '');

        if ($host === 'youtu.be' && preg_match('#^/([A-Za-z0-9_-]{11})#', $path, $m)) {
            return $m[1];
        }

        if (! self::isHost($host, ['youtube.com', 'youtube-nocookie.com'])) {
            return null;
        }

        if (preg_match('#^/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})#', $path, $m)) {
            return $m[1];
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        return $path === '/watch' && preg_match('/^[A-Za-z0-9_-]{11}$/', (string) ($query['v'] ?? '')) ? $query['v'] : null;
    }

    /** fb.watch/xyz, /watch?v=, /{page}/videos/{id}, /reel/{id}, /share/v|r/{code}, /video.php?v= */
    private static function looksLikeFacebookVideo(string $host, array $parts): bool
    {
        $path = (string) ($parts['path'] ?? '');

        if ($host === 'fb.watch') {
            return strlen(trim($path, '/')) > 0;
        }

        return (bool) preg_match('#^/(?:watch/?$|reel/|share/[vr]/|video\.php|[^/]+/videos/|videos/|.+/(?:videos|reel)/)#i', $path);
    }
}
