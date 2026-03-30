<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Character;

use App\Actions\Character\BuildCharacterCreateDataAction;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildCharacterCreateDataActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_selects_explicit_active_world_slug_and_skips_inactive_worlds(): void
    {
        $worldA = World::factory()->create([
            'name' => 'A-Welt',
            'slug' => 'a-welt',
            'position' => -100,
            'is_active' => true,
        ]);
        $worldB = World::factory()->create([
            'name' => 'B-Welt',
            'slug' => 'b-welt',
            'position' => -90,
            'is_active' => true,
        ]);
        $inactiveWorld = World::factory()->create([
            'name' => 'C-Welt',
            'slug' => 'c-welt',
            'position' => -110,
            'is_active' => false,
        ]);

        $result = app(BuildCharacterCreateDataAction::class)->execute('b-welt');

        $this->assertSame((int) $worldB->id, (int) ($result->selectedWorld?->id ?? 0));
        $worldIds = $result->worlds
            ->map(static fn (World $world): int => (int) $world->id)
            ->all();

        $this->assertContains((int) $worldA->id, $worldIds);
        $this->assertContains((int) $worldB->id, $worldIds);
        $this->assertNotContains((int) $inactiveWorld->id, $worldIds);
    }

    public function test_it_falls_back_to_first_active_world_for_unknown_slug(): void
    {
        $worldA = World::factory()->create([
            'name' => 'A-Welt',
            'slug' => 'a-welt',
            'position' => -100,
            'is_active' => true,
        ]);
        World::factory()->create([
            'name' => 'B-Welt',
            'slug' => 'b-welt',
            'position' => -90,
            'is_active' => true,
        ]);

        $result = app(BuildCharacterCreateDataAction::class)->execute('nicht-vorhanden');

        $this->assertSame((int) $worldA->id, (int) ($result->selectedWorld?->id ?? 0));
        $this->assertSame((int) $worldA->id, (int) ($result->worlds->first()?->id ?? 0));
    }
}
