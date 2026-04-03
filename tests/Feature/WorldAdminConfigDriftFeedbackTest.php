<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorldAdminConfigDriftFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_world_update_shows_slug_error_when_default_slug_config_is_missing_in_database(): void
    {
        $admin = User::factory()->admin()->create();
        $world = World::factory()->create([
            'slug' => 'drift-edit-target',
            'is_active' => true,
            'position' => 2400,
        ]);
        config(['worlds.default_slug' => 'fehlende-default-welt']);

        $this->actingAs($admin)
            ->from(route('admin.worlds.edit', $world))
            ->put(route('admin.worlds.update', $world), $this->worldUpdatePayload($world, [
                'name' => 'Drift Edit Target Prime',
            ]))
            ->assertRedirect(route('admin.worlds.edit', $world))
            ->assertSessionHasErrors('slug');

        $this->actingAs($admin)
            ->get(route('admin.worlds.edit', $world))
            ->assertOk()
            ->assertSee('data-world-admin-error-summary', false)
            ->assertSee('name="slug"', false)
            ->assertSeeText('Die Standardwelt-Konfiguration ist inkonsistent. Bitte worlds.default_slug und Datenbank synchronisieren.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function worldUpdatePayload(World $world, array $overrides = []): array
    {
        return array_merge([
            'name' => (string) $world->name,
            'slug' => (string) $world->slug,
            'tagline' => (string) ($world->tagline ?? ''),
            'description' => (string) ($world->description ?? ''),
            'position' => (int) $world->position,
            'is_active' => (bool) $world->is_active,
        ], $overrides);
    }
}

