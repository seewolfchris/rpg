<?php

namespace Tests\Feature;

use App\Exceptions\DefaultWorldConfigurationException;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorldContextActivationGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_world_is_not_reachable_via_public_world_show_route(): void
    {
        $world = World::factory()->create([
            'is_active' => false,
        ]);

        $this->get(route('worlds.show', ['world' => $world]))
            ->assertNotFound();
    }

    public function test_inactive_world_cannot_be_activated_via_public_route(): void
    {
        $world = World::factory()->create([
            'is_active' => false,
        ]);

        $this->post(route('worlds.activate', ['world' => $world]))
            ->assertNotFound()
            ->assertSessionMissing('world_slug');
    }

    public function test_home_request_with_inactive_session_world_slug_falls_back_to_active_default_world(): void
    {
        $defaultWorld = World::resolveDefault();
        $defaultWorld->update(['is_active' => true]);
        $inactiveWorld = World::factory()->create([
            'slug' => 'nachtmeer',
            'is_active' => false,
        ]);

        $this->withSession(['world_slug' => $inactiveWorld->slug])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('data-world-slug="'.$defaultWorld->slug.'"', false)
            ->assertSessionHas('world_slug', $defaultWorld->slug);
    }

    public function test_admin_can_open_edit_route_for_inactive_world(): void
    {
        $admin = User::factory()->admin()->create();
        $world = World::factory()->create([
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.worlds.edit', ['world' => $world]))
            ->assertOk();
    }

    public function test_home_request_fails_fast_when_default_world_slug_is_missing(): void
    {
        config(['worlds.default_slug' => 'nicht-vorhanden']);

        World::factory()->create([
            'slug' => 'aktive-ersatzwelt',
            'is_active' => true,
        ]);

        $this->withoutExceptionHandling();
        $this->expectException(DefaultWorldConfigurationException::class);
        $this->expectExceptionMessage("WORLD_DEFAULT_SLUG 'nicht-vorhanden' does not exist");

        $this->get(route('home'));
    }

    public function test_home_request_fails_fast_when_default_world_is_inactive(): void
    {
        config(['worlds.default_slug' => 'nachtmeer']);

        World::factory()->create([
            'slug' => 'nachtmeer',
            'is_active' => false,
        ]);

        $this->withoutExceptionHandling();
        $this->expectException(DefaultWorldConfigurationException::class);
        $this->expectExceptionMessage("WORLD_DEFAULT_SLUG 'nachtmeer' points to an inactive world");

        $this->get(route('home'));
    }

    public function test_home_request_fails_fast_when_worlds_table_is_missing(): void
    {
        Schema::shouldReceive('hasTable')
            ->with('worlds')
            ->andReturn(false);

        $this->withoutExceptionHandling();
        $this->expectException(DefaultWorldConfigurationException::class);
        $this->expectExceptionMessage("the 'worlds' table is missing");

        $this->get(route('home'));
    }
}
