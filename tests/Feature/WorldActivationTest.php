<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorldActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_activate_world_via_post_and_session_is_updated(): void
    {
        $world = World::factory()->create();

        $this->post(route('worlds.activate', ['world' => $world]))
            ->assertRedirect(route('worlds.show', ['world' => $world]))
            ->assertSessionHas('world_slug', $world->slug);
    }

    public function test_authenticated_user_activate_world_redirects_to_campaigns(): void
    {
        $user = User::factory()->create();
        $world = World::factory()->create();

        $this->actingAs($user)
            ->post(route('worlds.activate', ['world' => $world]))
            ->assertRedirect(route('campaigns.index', ['world' => $world]))
            ->assertSessionHas('world_slug', $world->slug);
    }

    public function test_world_activation_is_not_accessible_via_get(): void
    {
        $world = World::factory()->create();

        $this->get(route('worlds.activate', ['world' => $world]))
            ->assertStatus(405);
    }
}
