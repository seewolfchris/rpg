<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Character;

use App\Actions\Character\BuildCharacterEditDataAction;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildCharacterEditDataActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_active_worlds_in_order(): void
    {
        $activeA = World::factory()->create([
            'name' => 'A-Welt',
            'slug' => 'a-welt',
            'position' => -100,
            'is_active' => true,
        ]);
        $activeB = World::factory()->create([
            'name' => 'B-Welt',
            'slug' => 'b-welt',
            'position' => -90,
            'is_active' => true,
        ]);
        $inactive = World::factory()->create([
            'name' => 'C-Welt',
            'slug' => 'c-welt',
            'position' => -110,
            'is_active' => false,
        ]);

        $result = app(BuildCharacterEditDataAction::class)->execute();

        $worldIds = $result->worlds
            ->map(static fn (World $world): int => (int) $world->id)
            ->all();

        $this->assertGreaterThanOrEqual(2, count($worldIds));
        $this->assertSame((int) $activeA->id, $worldIds[0] ?? 0);
        $this->assertSame((int) $activeB->id, $worldIds[1] ?? 0);
        $this->assertContains((int) $activeA->id, $worldIds);
        $this->assertContains((int) $activeB->id, $worldIds);
        $this->assertNotContains((int) $inactive->id, $worldIds);
    }
}
