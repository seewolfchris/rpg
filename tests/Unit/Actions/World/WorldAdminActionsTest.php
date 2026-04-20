<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\World;

use App\Actions\World\CreateWorldAction;
use App\Actions\World\DeleteWorldAction;
use App\Actions\World\ReorderWorldsAction;
use App\Models\Campaign;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorldAdminActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_world_persists_payload(): void
    {
        $world = app(CreateWorldAction::class)->execute([
            'name' => 'Nachtklippen',
            'slug' => 'nachtklippen',
            'tagline' => 'Dunkel',
            'description' => 'Beschreibung',
            'position' => 500,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('worlds', [
            'id' => $world->id,
            'slug' => 'nachtklippen',
            'is_active' => true,
        ]);
    }

    public function test_delete_world_rejects_default_world(): void
    {
        $defaultWorld = World::query()
            ->where('slug', World::defaultSlug())
            ->firstOrFail();

        $this->expectException(ValidationException::class);

        app(DeleteWorldAction::class)->execute($defaultWorld);
    }

    public function test_delete_world_rejects_existing_dependencies(): void
    {
        $owner = User::factory()->gm()->create();
        $world = World::factory()->create();

        Campaign::factory()->create([
            'world_id' => $world->id,
            'owner_id' => $owner->id,
        ]);

        $this->expectException(ValidationException::class);

        app(DeleteWorldAction::class)->execute($world);
    }

    public function test_reorder_worlds_moves_world_up_and_updates_positions(): void
    {
        $worldA = World::factory()->create(['position' => 1000, 'name' => 'A']);
        $worldB = World::factory()->create(['position' => 1010, 'name' => 'B']);
        $worldC = World::factory()->create(['position' => 1020, 'name' => 'C']);

        $moved = app(ReorderWorldsAction::class)->execute($worldB, 'up');

        $orderedIds = World::query()
            ->whereIn('id', [$worldA->id, $worldB->id, $worldC->id])
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $this->assertTrue($moved);
        $this->assertSame([(int) $worldB->id, (int) $worldA->id, (int) $worldC->id], $orderedIds);
    }

    public function test_reorder_worlds_returns_false_at_upper_boundary(): void
    {
        $topWorld = World::query()
            ->ordered()
            ->orderBy('id')
            ->firstOrFail();

        $moved = app(ReorderWorldsAction::class)->execute($topWorld, 'up');

        $this->assertFalse($moved);
    }
}
