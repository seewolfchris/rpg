<?php

namespace Tests\Unit;

use App\Support\PostContentRenderer;
use Tests\TestCase;

class PostContentRendererTest extends TestCase
{
    public function test_markdown_rendering_strips_unsafe_html_and_supports_spoilers(): void
    {
        $renderer = app(PostContentRenderer::class);

        $html = $renderer
            ->render("**Ruf des Stahls**\n\n[spoiler]Geheim <script>alert(1)</script>[/spoiler]", 'markdown')
            ->toHtml();

        $this->assertStringContainsString('<strong>Ruf des Stahls</strong>', $html);
        $this->assertStringContainsString('<details', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_bbcode_rendering_supports_tags_and_blocks_unsafe_links(): void
    {
        $renderer = app(PostContentRenderer::class);

        $html = $renderer
            ->render('[b]Stahl[/b] und [i]Nebel[/i] [url=https://example.org]Archiv[/url] [url=javascript:alert(1)]Falle[/url]', 'bbcode')
            ->toHtml();

        $this->assertStringContainsString('<strong>Stahl</strong>', $html);
        $this->assertStringContainsString('<em>Nebel</em>', $html);
        $this->assertStringContainsString('href="https://example.org"', $html);
        $this->assertStringNotContainsString('javascript:alert(1)', $html);
        $this->assertStringContainsString('Falle', $html);
    }

    public function test_plain_rendering_escapes_html_and_keeps_line_breaks(): void
    {
        $renderer = app(PostContentRenderer::class);

        $html = $renderer
            ->render("<b>Flamme</b>\nAsche", 'plain')
            ->toHtml();

        $this->assertStringContainsString('&lt;b&gt;Flamme&lt;/b&gt;<br>', $html);
        $this->assertStringContainsString('Asche', $html);
    }
}
