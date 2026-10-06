<?php

namespace App\Support;

/**
 * Turns an admin-pasted video link into something safe to embed: a
 * YouTube/Vimeo iframe URL or a direct .mp4/.webm file. Anything else (or a
 * non-http(s) URL) returns null and the page simply shows no video.
 */
class VideoEmbed
{
    /** @return array{type: 'iframe'|'video', src: string}|null */
    public static function parse(?string $url): ?array
    {
        $url = trim((string) $url);

        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        if (preg_match('#(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/))([A-Za-z0-9_-]{11})#', $url, $m)) {
            return ['type' => 'iframe', 'src' => "https://www.youtube.com/embed/{$m[1]}?rel=0"];
        }

        if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
            return ['type' => 'iframe', 'src' => "https://player.vimeo.com/video/{$m[1]}"];
        }

        if (preg_match('#\.(mp4|webm)(\?.*)?$#i', $url)) {
            return ['type' => 'video', 'src' => $url];
        }

        return null;
    }
}
