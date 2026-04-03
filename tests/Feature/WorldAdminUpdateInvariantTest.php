<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorldAdminUpdateInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_world_update_rejects_default_world_slug_change(): void
    {
        $admin = User::factory()->admin()->create();
        $defaultWorld = World::query()
            ->where('slug', (string) config('worlds.default_slug'))
            ->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.worlds.edit', $defaultWorld))
            ->put(route('admin.worlds.update', $defaultWorld), $this->worldUpdatePayload($defaultWorld, [
                'slug' => 'veraenderte-standardwelt',
            ]))
            ->assertRedirect(route('admin.worlds.edit', $defaultWorld))
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseHas('worlds', [
            'id' => $defaultWorld->id,
            'slug' => (string) config('worlds.default_slug'),
        ]);
    }

    public function test_admin_world_update_rejects_default_world_deactivation(): void
    {
        $admin = User::factory()->admin()->create();
        $defaultWorld = World::query()
            ->where('slug', (string) config('worlds.default_slug'))
            ->firstOrFail();
        World::factory()->create([
            'slug' => 'nebenwelt-aktiv',
            'is_active' => true,
            'position' => 999,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.worlds.edit', $defaultWorld))
            ->put(route('admin.worlds.update', $defaultWorld), $this->worldUpdatePayload($defaultWorld, [
                'is_active' => false,
            ]))
            ->assertRedirect(route('admin.worlds.edit', $defaultWorld))
            ->assertSessionHasErrors('is_active');

        $this->assertDatabaseHas('worlds', [
            'id' => $defaultWorld->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_world_update_rejects_deactivation_of_last_active_world(): void
    {
        $admin = User::factory()->admin()->create();
        $targetWorld = World::factory()->create([
            'slug' => 'letzte-aktive-welt',
            'is_active' => true,
            'position' => 1400,
        ]);
        World::query()
            ->whereKeyNot($targetWorld->id)
            ->update(['is_active' => false]);

        $this->actingAs($admin)
            ->from(route('admin.worlds.edit', $targetWorld))
            ->put(route('admin.worlds.update', $targetWorld), $this->worldUpdatePayload($targetWorld, [
                'is_active' => false,
            ]))
            ->assertRedirect(route('admin.worlds.edit', $targetWorld))
            ->assertSessionHasErrors('is_active');

        $this->assertDatabaseHas('worlds', [
            'id' => $targetWorld->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_world_edit_form_renders_slug_invariant_error_message(): void
    {
        $admin = User::factory()->admin()->create();
        $defaultWorld = World::query()
            ->where('slug', (string) config('worlds.default_slug'))
            ->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.worlds.edit', $defaultWorld))
            ->put(route('admin.worlds.update', $defaultWorld), $this->worldUpdatePayload($defaultWorld, [
                'slug' => 'abweichender-default-slug',
            ]))
            ->assertRedirect(route('admin.worlds.edit', $defaultWorld))
            ->assertSessionHasErrors('slug');

        $this->actingAs($admin)
            ->get(route('admin.worlds.edit', $defaultWorld))
            ->assertOk()
            ->assertSee('data-world-admin-error-summary', false)
            ->assertSee('name="slug"', false)
            ->assertSeeText('Der Slug der Standardwelt kann nicht geändert werden.');
    }

    public function test_admin_world_edit_form_renders_is_active_invariant_error_message_for_htmx_request(): void
    {
        $admin = User::factory()->admin()->create();
        $defaultWorld = World::query()
            ->where('slug', (string) config('worlds.default_slug'))
            ->firstOrFail();
        World::factory()->create([
            'slug' => 'aktiver-nebenpfad',
            'is_active' => true,
            'position' => 1600,
        ]);

        $this->actingAs($admin)
            ->withHeaders(['HX-Request' => 'true', 'HX-Target' => 'world-edit-form'])
            ->from(route('admin.worlds.edit', $defaultWorld))
            ->put(route('admin.worlds.update', $defaultWorld), $this->worldUpdatePayload($defaultWorld, [
                'is_active' => false,
            ]))
            ->assertRedirect(route('admin.worlds.edit', $defaultWorld))
            ->assertSessionHasErrors('is_active');

        $this->actingAs($admin)
            ->withHeaders(['HX-Request' => 'true'])
            ->get(route('admin.worlds.edit', $defaultWorld))
            ->assertOk()
            ->assertSee('data-world-admin-error-summary', false)
            ->assertSee('name="is_active"', false)
            ->assertSeeText('Die Standardwelt kann nicht deaktiviert werden.');
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
