<?php

namespace Tests\Feature;

use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorldThemeContextFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_world_route_renders_world_slug_and_theme_attributes_on_root(): void
    {
        $world = World::resolveDefault();

        $response = $this->get(route('worlds.show', ['world' => $world]));

        $response->assertOk()
            ->assertSee('data-world-slug="chroniken-der-asche"', false)
            ->assertSee('data-world-theme="chroniken-der-asche"', false)
            ->assertSee('--world-bg-top:', false)
            ->assertSee('<meta name="theme-color" content="#18110f">', false);
    }

    public function test_home_route_uses_session_world_slug_for_root_context(): void
    {
        World::factory()->create(['slug' => 'nachtmeer']);

        $response = $this->withSession(['world_slug' => 'nachtmeer'])
            ->get(route('home'));

        $response->assertOk()
            ->assertSee('data-world-slug="nachtmeer"', false);
    }
}
