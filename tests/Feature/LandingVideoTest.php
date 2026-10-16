<?php

namespace Tests\Feature;

use App\Models\LandingPage;
use App\Support\VideoEmbed;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/** YouTube / Facebook links, the hero's video option and the standalone video block — loaded only when tapped. */
class LandingVideoTest extends TestCase
{
    use CreatesShopData;

    private const YT = 'dQw4w9WgXcQ';

    public function test_youtube_links_in_every_common_form(): void
    {
        foreach ([
            'https://www.youtube.com/watch?v='.self::YT,
            'https://youtube.com/watch?feature=share&v='.self::YT,
            'https://m.youtube.com/watch?v='.self::YT.'&t=10s',
            'https://youtu.be/'.self::YT.'?si=abc',
            'https://www.youtube.com/shorts/'.self::YT,
            'https://www.youtube.com/embed/'.self::YT,
            'https://www.youtube.com/live/'.self::YT,
        ] as $url) {
            $v = VideoEmbed::parse($url);
            $this->assertNotNull($v, $url);
            $this->assertSame('youtube', $v['provider'], $url);
            $this->assertStringStartsWith('https://www.youtube.com/embed/'.self::YT, $v['src']);
            $this->assertStringContainsString('autoplay=1', $v['play']);
            $this->assertStringNotContainsString('autoplay', $v['src']);
            $this->assertSame('https://i.ytimg.com/vi/'.self::YT.'/hqdefault.jpg', $v['thumb']);
        }
    }

    public function test_facebook_links_in_every_common_form(): void
    {
        foreach ([
            'https://www.facebook.com/AmarShop/videos/1234567890123/',
            'https://facebook.com/watch/?v=1234567890123',
            'https://web.facebook.com/watch?v=1234567890123',
            'https://m.facebook.com/AmarShop/videos/1234567890123',
            'https://www.facebook.com/reel/1234567890123',
            'https://www.facebook.com/share/v/AbCdEf123/',
            'https://www.facebook.com/share/r/AbCdEf123/',
            'https://fb.watch/abCDef12/',
            'http://www.facebook.com/AmarShop/videos/1234567890123/',
        ] as $url) {
            $v = VideoEmbed::parse($url);
            $this->assertNotNull($v, $url);
            $this->assertSame('facebook', $v['provider'], $url);
            $this->assertSame('iframe', $v['type']);
            $this->assertStringStartsWith('https://www.facebook.com/plugins/video.php?href=https%3A%2F%2F', $v['src'], $url);
            $this->assertStringContainsString('autoplay=true', $v['play']);
            $this->assertNull($v['thumb'], 'Facebook gives no free cover picture');
        }
    }

    public function test_vimeo_and_direct_files_still_work_and_everything_else_is_refused(): void
    {
        $this->assertSame('vimeo', VideoEmbed::parse('https://vimeo.com/123456789')['provider']);
        $this->assertSame('vimeo', VideoEmbed::parse('https://player.vimeo.com/video/123456789')['provider']);
        $this->assertSame('file', VideoEmbed::parse('https://cdn.example.com/a/clip.mp4?x=1')['provider']);
        $this->assertSame('file', VideoEmbed::parse('https://cdn.example.com/clip.WEBM')['provider']);

        foreach ([
            '', 'not a link', 'javascript:alert(1)', 'ftp://youtube.com/watch?v='.self::YT,
            'https://evil.example/?x=youtube.com/watch?v='.self::YT,
            'https://youtube.com.evil.example/watch?v='.self::YT,
            'https://facebook.com.evil.example/AmarShop/videos/1234',
            'https://evil.example/facebook.com/watch/?v=1',
            'https://www.facebook.com/AmarShop',            // a page, not a video
            'https://www.youtube.com/channel/UC1234567890',  // not a video
            'https://example.com/page.html',
        ] as $bad) {
            $this->assertNull(VideoEmbed::parse($bad), $bad);
        }
    }

    private function page(string $template, array $blocks): LandingPage
    {
        $product = $this->product();

        return LandingPage::create([
            'title' => 'Vid', 'slug' => 'vid-'.uniqid(), 'template' => $template, 'headline' => 'Vid',
            'product_id' => $product->id, 'is_active' => true, 'blocks' => $blocks,
        ]);
    }

    public function test_the_video_block_shows_a_cover_and_loads_nothing_until_tapped(): void
    {
        foreach (['https://youtu.be/'.self::YT, 'https://www.facebook.com/AmarShop/videos/1234567890123/'] as $link) {
            $lp = $this->page('green', [['type' => 'video', 'data' => ['heading' => 'দেখুন', 'url' => $link]]]);

            $html = $this->get('/lp/'.$lp->slug)->assertOk()->getContent();

            $this->assertStringContainsString('aspect-video', $html, '16:9');
            $this->assertStringContainsString('rounded-none md:rounded-xl', $html, 'edge to edge on a phone');
            $this->assertStringContainsString('x-data="{ on: false }"', $html, 'starts as a cover, not a player');
            $this->assertStringContainsString('ভিডিও চালান', $html);
        }

        // The player markup is only inside Alpine's x-if template — nothing is fetched on page load.
        $lp = $this->page('green', [['type' => 'video', 'data' => ['url' => 'https://youtu.be/'.self::YT]]]);
        $html = $this->get('/lp/'.$lp->slug)->getContent();
        $this->assertMatchesRegularExpression('#<template x-if="on">\s*<iframe#', $html);
        $this->assertStringContainsString('https://i.ytimg.com/vi/'.self::YT.'/hqdefault.jpg', $html, 'YouTube cover picture');
    }

    public function test_an_unusable_link_shows_no_video_block(): void
    {
        $lp = $this->page('green', [['type' => 'video', 'data' => ['url' => 'https://example.com/page.html']]]);

        $this->get('/lp/'.$lp->slug)->assertOk()->assertDontSee('ভিডিও চালান');
    }

    public function test_the_hero_can_show_the_video_instead_of_the_picture_in_every_template(): void
    {
        foreach (['purple', 'green', 'cream'] as $template) {
            $lp = $this->page($template, [['type' => 'hero', 'data' => [
                'headline' => 'Hero', 'images' => ['/storage/media/hero-photo.png'],
                'video_url' => 'https://www.facebook.com/AmarShop/videos/1234567890123/', 'hero_media' => 'video',
            ]]]);

            $html = $this->get('/lp/'.$lp->slug)->assertOk()->getContent();

            $this->assertStringContainsString('x-data="{ on: false }"', $html, $template);
            $this->assertStringContainsString('facebook.com/plugins/video.php', $html, $template);
            $this->assertStringContainsString('-mx-4 md:mx-0', $html, "$template: full width on a phone");
            // The picture becomes the video's cover; there is no slider and no ▶-over-slider popup.
            $this->assertStringContainsString('hero-photo.png', $html, $template);
            $this->assertStringNotContainsString('translateX(-', $html, $template);
        }
    }

    public function test_the_hero_keeps_the_picture_with_a_play_popup_by_default_and_a_video_alone_shows_the_video(): void
    {
        $withImage = $this->page('green', [['type' => 'hero', 'data' => [
            'headline' => 'Hero', 'images' => ['/storage/media/hero-photo.png'], 'video_url' => 'https://youtu.be/'.self::YT,
        ]]]);
        $html = $this->get('/lp/'.$withImage->slug)->assertOk()->getContent();
        $this->assertStringContainsString('translateX(-', $html, 'slider is still there');
        $this->assertStringContainsString('aria-label="ভিডিও চালান"', $html, 'with the play button');
        $this->assertStringContainsString("x-data=\"{ on: true }\"", $html, 'the popup player starts at once');

        $videoOnly = $this->page('green', [['type' => 'hero', 'data' => ['headline' => 'Hero', 'video_url' => 'https://youtu.be/'.self::YT]]]);
        $html = $this->get('/lp/'.$videoOnly->slug)->assertOk()->getContent();
        $this->assertStringContainsString('x-data="{ on: false }"', $html, 'no picture, so the video itself is shown');
    }
}
