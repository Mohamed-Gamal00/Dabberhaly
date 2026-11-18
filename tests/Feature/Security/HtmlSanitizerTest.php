<?php

namespace Tests\Feature\Security;

use App\Services\HtmlSanitizer;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_strips_scripts_iframes_and_event_handlers(): void
    {
        $dirty = '<p>Hello</p><script>alert(1)</script>'
            . '<img src="x" onerror="alert(2)">'
            . '<iframe src="https://evil.example"></iframe>'
            . '<a href="javascript:alert(3)">click</a>'
            . '</textarea><script>alert(4)</script>';

        $clean = HtmlSanitizer::purify($dirty);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('</textarea>', $clean);
        $this->assertStringContainsString('<p>Hello</p>', $clean);
    }

    public function test_keeps_safe_formatting(): void
    {
        $html = '<h2>Title</h2><p>Text with <strong>bold</strong> and '
            . '<a href="https://example.com">a link</a>.</p>'
            . '<ul><li>one</li><li>two</li></ul>';

        $clean = HtmlSanitizer::purify($html);

        $this->assertStringContainsString('<h2>Title</h2>', $clean);
        $this->assertStringContainsString('<strong>bold</strong>', $clean);
        $this->assertStringContainsString('<a href="https://example.com">a link</a>', $clean);
        $this->assertStringContainsString('<li>two</li>', $clean);
    }
}
