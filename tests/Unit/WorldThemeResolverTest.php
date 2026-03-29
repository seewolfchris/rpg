<?php

namespace Tests\Unit;

use App\Support\WorldThemeResolver;
use Tests\TestCase;

class WorldThemeResolverTest extends TestCase
{
    public function test_unknown_slug_uses_default_profile(): void
    {
        $resolver = app(WorldThemeResolver::class);
        $resolved = $resolver->resolve('unbekannte-welt');

        $this->assertSame('unbekannte-welt', $resolved['world_slug']);
        $this->assertSame('default', $resolved['theme_key']);
        $this->assertNotSame('', $resolved['css_variable_style']);
        $this->assertArrayHasKey('--world-bg-top', $resolved['css_variables']);
    }

    public function test_chroniken_der_asche_profile_overrides_default_values(): void
    {
        $resolver = app(WorldThemeResolver::class);
        $resolved = $resolver->resolve('chroniken-der-asche');

        $this->assertSame('chroniken-der-asche', $resolved['theme_key']);
        $this->assertSame('#18110f', $resolved['theme_color']);
        $this->assertSame('Chroniken der Asche', $resolved['label']);
    }
}
