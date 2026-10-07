<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Sanitizer for admin-written rich text (product descriptions, policy pages).
 * Admins are trusted, but a stolen admin session must not turn into stored
 * XSS on every visitor's browser, so output is rebuilt from an allow-list of
 * tags and attributes: scripts/styles/iframes are dropped, unknown tags are
 * unwrapped, event-handler attributes and javascript: URLs never survive.
 */
class Html
{
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'svg', 'math'];

    /** tag => allowed attributes */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'blockquote' => [],
        'pre' => [], 'code' => [], 'span' => [], 'div' => [], 'sub' => [], 'sup' => [], 'mark' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'width', 'height'],
    ];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__root');

        if (! $root) {
            return e($html);
        }

        self::sanitizeChildren($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    /** True when the text already contains HTML tags (rich text) rather than plain text. */
    public static function looksLikeHtml(?string $text): bool
    {
        return (bool) preg_match('/<\s*\/?\s*[a-z][^>]*>/i', (string) $text);
    }

    /** Rich text as safe HTML; legacy plain text keeps its line breaks. */
    public static function render(?string $text): string
    {
        return self::looksLikeHtml($text) ? self::clean($text) : nl2br(e((string) $text));
    }

    private static function sanitizeChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }

            self::sanitizeChildren($child);

            if (! array_key_exists($tag, self::ALLOWED)) {
                // Unknown tag: keep its (already sanitized) content, lose the tag.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->nodeName);

                if (! in_array($name, self::ALLOWED[$tag], true)) {
                    $child->removeAttribute($attr->nodeName);

                    continue;
                }

                if (in_array($name, ['href', 'src'], true) && ! self::safeUrl($attr->nodeValue, $name === 'src')) {
                    $child->removeAttribute($attr->nodeName);
                }
            }

            if ($tag === 'a' && $child->hasAttribute('href')) {
                $child->setAttribute('rel', 'noopener nofollow');
                if (preg_match('#^https?://#i', $child->getAttribute('href'))) {
                    $child->setAttribute('target', '_blank');
                }
            }
        }
    }

    private static function safeUrl(string $url, bool $isImage): bool
    {
        $url = trim(preg_replace('/[\x00-\x20]+/', '', $url));

        if ($url === '') {
            return false;
        }

        if (preg_match('#^(https?:)?//#i', $url) || str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        return ! $isImage && (bool) preg_match('#^(mailto|tel):#i', $url);
    }
}
