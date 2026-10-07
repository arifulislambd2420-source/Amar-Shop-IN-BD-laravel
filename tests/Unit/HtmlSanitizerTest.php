<?php

namespace Tests\Unit;

use App\Support\Html;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_dangerous_markup_is_removed_and_safe_markup_is_kept(): void
    {
        $dirty = '<p onclick="x()">Hello <strong>world</strong></p><script>alert(1)</script>'
            .'<a href="javascript:alert(1)">bad</a><a href="https://example.com">ok</a>'
            .'<img src="x" onerror="alert(1)"><iframe src="https://evil"></iframe><style>p{}</style>';

        $clean = Html::clean($dirty);

        $this->assertStringContainsString('<strong>world</strong>', $clean);
        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('iframe', $clean);
        $this->assertStringNotContainsString('<style', $clean);
    }

    public function test_plain_text_is_escaped_with_line_breaks(): void
    {
        $this->assertStringContainsString('<br', Html::render("line1\nline2"));
        $this->assertStringContainsString('&amp;', Html::render('Tom & Jerry'));
        $this->assertFalse(Html::looksLikeHtml('just text'));
        $this->assertTrue(Html::looksLikeHtml('<p>rich</p>'));
    }

    public function test_bangla_text_survives(): void
    {
        $this->assertStringContainsString('খাঁটি মধু', Html::clean('<p>খাঁটি মধু</p>'));
    }
}
