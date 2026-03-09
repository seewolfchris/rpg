<?php

namespace Tests\Unit;

use App\Support\EncyclopediaContentRenderer;
use Tests\TestCase;

class EncyclopediaContentRendererTest extends TestCase
{
    public function test_renderer_strips_unsafe_html(): void
    {
        $renderer = app(EncyclopediaContentRenderer::class);

        $html = $renderer->render('**Asche** <script>alert(1)</script>')->toHtml();

        $this->assertStringContainsString('<strong>Asche</strong>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_renderer_blocks_unsafe_links_but_keeps_safe_links(): void
    {
        $renderer = app(EncyclopediaContentRenderer::class);

        $html = $renderer->render('[Archiv](https://example.org) [Falle](javascript:alert(1))')->toHtml();

        $this->assertStringContainsString('href="https://example.org"', $html);
        $this->assertStringNotContainsString('javascript:alert(1)', $html);
        $this->assertStringContainsString('Falle', $html);
    }

    public function test_renderer_returns_default_text_for_empty_content(): void
    {
        $renderer = app(EncyclopediaContentRenderer::class);

        $html = $renderer->render('   ')->toHtml();

        $this->assertStringContainsString('Kein Inhalt.', $html);
    }
}
