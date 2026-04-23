<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Character;

use App\Actions\Character\BuildCharacterIndexDataAction;
use App\Models\Character;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildCharacterIndexDataActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_filters_by_world_and_owner_for_player_and_normalizes_invalid_status(): void
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

        $player = User::factory()->create();
        $otherUser = User::factory()->create();

        $playerActiveA = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $worldA->id,
            'status' => 'active',
            'name' => 'Spieler A Aktiv',
        ]);
        $playerPauseA = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $worldA->id,
            'status' => 'pause',
            'name' => 'Spieler A Pause',
        ]);
        Character::factory()->create([
            'user_id' => $otherUser->id,
            'world_id' => $worldA->id,
            'status' => 'active',
            'name' => 'Fremd A Aktiv',
        ]);
        Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $worldB->id,
            'status' => 'active',
            'name' => 'Spieler B Aktiv',
        ]);

        $result = app(BuildCharacterIndexDataAction::class)->execute(
            user: $player,
            selectedWorldSlug: 'a-welt',
            selectedStatus: 'ungueltig',
        );

        $this->assertSame('all', $result->selectedStatus);
        $this->assertSame((int) $worldA->id, (int) ($result->selectedWorld?->id ?? 0));
        $this->assertGreaterThanOrEqual(2, $result->worlds->count());
        $this->assertSame((int) $worldA->id, (int) ($result->worlds->first()?->id ?? 0));
        $worldIds = $result->worlds
            ->map(static fn (World $world): int => (int) $world->id)
            ->all();
        $this->assertContains((int) $worldA->id, $worldIds);
        $this->assertContains((int) $worldB->id, $worldIds);
        $this->assertSame(2, $result->characters->total());

        $characterIds = collect($result->characters->items())
            ->map(static fn (Character $character): int => (int) $character->id)
            ->all();
        $this->assertContains((int) $playerActiveA->id, $characterIds);
        $this->assertContains((int) $playerPauseA->id, $characterIds);
        $this->assertTrue($result->characters->getCollection()->first()?->relationLoaded('user') ?? false);
        $this->assertTrue($result->characters->getCollection()->first()?->relationLoaded('world') ?? false);
    }

    public function test_it_falls_back_to_first_active_world_and_applies_status_filter_for_admin(): void
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

        $admin = User::factory()->admin()->create();
        $player = User::factory()->create();

        $activeA = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $worldA->id,
            'status' => 'active',
            'name' => 'Aktiv A',
        ]);
        $pauseA = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $worldA->id,
            'status' => 'pause',
            'name' => 'Pause A',
        ]);
        Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $worldB->id,
            'status' => 'active',
            'name' => 'Aktiv B',
        ]);

        $result = app(BuildCharacterIndexDataAction::class)->execute(
            user: $admin,
            selectedWorldSlug: 'nicht-vorhanden',
            selectedStatus: 'active',
        );

        $this->assertSame('active', $result->selectedStatus);
        $this->assertSame((int) $worldA->id, (int) ($result->selectedWorld?->id ?? 0));
        $this->assertSame((int) $worldA->id, (int) ($result->worlds->first()?->id ?? 0));
        $this->assertSame(1, $result->characters->total());

        $resolvedCharacter = $result->characters->getCollection()->first();
        $this->assertInstanceOf(Character::class, $resolvedCharacter);
        $this->assertSame((int) $activeA->id, (int) $resolvedCharacter->id);
        $this->assertNotSame((int) $pauseA->id, (int) $resolvedCharacter->id);
    }
}
