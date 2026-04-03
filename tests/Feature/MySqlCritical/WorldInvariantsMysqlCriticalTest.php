<?php

namespace Tests\Feature\MySqlCritical;

use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('mysql-critical')]
class WorldInvariantsMysqlCriticalTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_world_slug_cannot_be_changed(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only critical test.');
        }

        $admin = User::factory()->admin()->create();
        $defaultWorld = World::query()
            ->where('slug', World::defaultSlug())
            ->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.worlds.edit', $defaultWorld))
            ->put(route('admin.worlds.update', $defaultWorld), $this->payload($defaultWorld, [
                'slug' => 'kritischer-default-slug',
            ]))
            ->assertRedirect(route('admin.worlds.edit', $defaultWorld))
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseHas('worlds', [
            'id' => $defaultWorld->id,
            'slug' => World::defaultSlug(),
        ]);
    }

    public function test_default_world_cannot_be_deactivated(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only critical test.');
        }

        $admin = User::factory()->admin()->create();
        $defaultWorld = World::query()
            ->where('slug', World::defaultSlug())
            ->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.worlds.edit', $defaultWorld))
            ->put(route('admin.worlds.update', $defaultWorld), $this->payload($defaultWorld, [
                'is_active' => false,
            ]))
            ->assertRedirect(route('admin.worlds.edit', $defaultWorld))
            ->assertSessionHasErrors('is_active');

        $this->assertDatabaseHas('worlds', [
            'id' => $defaultWorld->id,
            'is_active' => true,
        ]);
    }

    public function test_last_active_world_cannot_be_deactivated(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only critical test.');
        }

        $admin = User::factory()->admin()->create();
        $targetWorld = World::factory()->create([
            'slug' => 'mysql-critical-letzte-aktive',
            'is_active' => true,
            'position' => 10000,
        ]);

        World::query()
            ->whereKeyNot($targetWorld->id)
            ->update(['is_active' => false]);

        $this->actingAs($admin)
            ->from(route('admin.worlds.edit', $targetWorld))
            ->put(route('admin.worlds.update', $targetWorld), $this->payload($targetWorld, [
                'is_active' => false,
            ]))
            ->assertRedirect(route('admin.worlds.edit', $targetWorld))
            ->assertSessionHasErrors('is_active');

        $this->assertDatabaseHas('worlds', [
            'id' => $targetWorld->id,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(World $world, array $overrides = []): array
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
