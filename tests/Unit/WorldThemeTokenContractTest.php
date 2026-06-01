<?php

namespace Tests\Unit;

use App\Support\WorldThemeResolver;
use Tests\TestCase;

class WorldThemeTokenContractTest extends TestCase
{
    public function test_resolver_exposes_marker_tokens_for_configured_world_theme(): void
    {
        $resolved = app(WorldThemeResolver::class)->resolve('chroniken-der-asche');

        $this->assertSame('Chroniken der Asche', $resolved['label']);
        $this->assertSame('CA', $resolved['marker_label']);
        $this->assertSame('ASCHE', $resolved['marker_symbol']);
        $this->assertNotSame('', $resolved['marker_bg']);
        $this->assertNotSame('', $resolved['marker_fg']);
        $this->assertNotSame('', $resolved['marker_border']);
    }

    public function test_resolver_returns_marker_defaults_for_unknown_world_theme(): void
    {
        $resolved = app(WorldThemeResolver::class)->resolve('unbekannte-welt');

        $this->assertSame('unbekannte-welt', $resolved['world_slug']);
        $this->assertSame('default', $resolved['theme_key']);
        $this->assertNotSame('', $resolved['css_variable_style']);
        $this->assertNotSame('', $resolved['marker_label']);
        $this->assertNotSame('', $resolved['marker_symbol']);
        $this->assertNotSame('', $resolved['marker_bg']);
        $this->assertNotSame('', $resolved['marker_fg']);
        $this->assertNotSame('', $resolved['marker_border']);
    }
}
