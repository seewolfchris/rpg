<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorldAdminUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_toggle_world_active_state(): void
    {
        $admin = User::factory()->admin()->create();
        $otherWorld = World::factory()->create([
            'slug' => 'nebel-kronen',
            'is_active' => true,
            'position' => 20,
        ]);
        World::factory()->create([
            'slug' => 'runen-marken',
            'is_active' => true,
            'position' => 30,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.worlds.toggle-active', $otherWorld))
            ->assertRedirect(route('admin.worlds.index'));

        $this->assertDatabaseHas('worlds', [
            'id' => $otherWorld->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_cannot_deactivate_last_active_world(): void
    {
        $admin = User::factory()->admin()->create();
        World::query()->update(['is_active' => false]);

        $world = World::query()
            ->where('slug', '!=', (string) config('worlds.default_slug'))
            ->firstOrFail();
        $world->update(['is_active' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.worlds.toggle-active', $world))
            ->assertSessionHasErrors('world');

        $this->assertDatabaseHas('worlds', [
            'id' => $world->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_deactivate_default_world(): void
    {
        $admin = User::factory()->admin()->create();
        $defaultWorld = World::query()
            ->where('slug', (string) config('worlds.default_slug'))
            ->firstOrFail();
        $defaultWorld->update(['is_active' => true]);

        World::factory()->create([
            'slug' => 'zweite-welt',
            'is_active' => true,
            'position' => 999,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.worlds.toggle-active', $defaultWorld))
            ->assertSessionHasErrors('world');

        $this->assertDatabaseHas('worlds', [
            'id' => $defaultWorld->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_delete_default_world(): void
    {
        $admin = User::factory()->admin()->create();
        $defaultWorld = World::query()
            ->where('slug', (string) config('worlds.default_slug'))
            ->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.worlds.destroy', $defaultWorld))
            ->assertSessionHasErrors('world');

        $this->assertDatabaseHas('worlds', [
            'id' => $defaultWorld->id,
            'slug' => (string) config('worlds.default_slug'),
        ]);
    }

    public function test_admin_can_reorder_worlds_with_move_actions(): void
    {
        $admin = User::factory()->admin()->create();
        $worldA = World::factory()->create([
            'name' => 'Aschewelt',
            'slug' => 'aschewelt',
            'position' => 1000,
            'is_active' => true,
        ]);
        $worldB = World::factory()->create([
            'name' => 'Bergwelt',
            'slug' => 'bergwelt',
            'position' => 1010,
            'is_active' => true,
        ]);
        $worldC = World::factory()->create([
            'name' => 'Cryptwelt',
            'slug' => 'cryptwelt',
            'position' => 1020,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.worlds.move', ['world' => $worldB, 'direction' => 'up']))
            ->assertRedirect(route('admin.worlds.index'));

        $orderedIds = World::query()
            ->ordered()
            ->whereIn('id', [$worldA->id, $worldB->id, $worldC->id])
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $this->assertSame([$worldB->id, $worldA->id, $worldC->id], $orderedIds);
    }

    public function test_non_admin_cannot_use_world_admin_quick_actions(): void
    {
        $player = User::factory()->create();
        $world = World::factory()->create();

        $this->actingAs($player)
            ->patch(route('admin.worlds.toggle-active', $world))
            ->assertForbidden();

        $this->actingAs($player)
            ->patch(route('admin.worlds.move', ['world' => $world, 'direction' => 'up']))
            ->assertForbidden();
    }
}
