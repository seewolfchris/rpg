<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Character;

use App\Actions\Character\BuildCharacterShowDataAction;
use App\Domain\Character\CharacterProgressionService;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\CharacterInventoryLog;
use App\Models\CharacterProgressionEvent;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildCharacterShowDataActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_show_data_with_expected_limits_relations_and_progression_state(): void
    {
        $owner = User::factory()->gm()->create();
        $actor = User::factory()->create();
        $character = Character::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Zeige-Daten',
        ]);
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
        ]);

        for ($index = 1; $index <= 30; $index++) {
            CharacterInventoryLog::query()->create([
                'character_id' => $character->id,
                'actor_user_id' => $actor->id,
                'source' => 'character_sheet_update',
                'action' => 'add',
                'item_name' => 'Item '.$index,
                'quantity' => 1,
                'equipped' => false,
                'note' => null,
                'context' => ['slot' => $index],
                'created_at' => now()->subMinutes(31 - $index),
            ]);
        }

        for ($index = 1; $index <= 24; $index++) {
            CharacterProgressionEvent::query()->create([
                'character_id' => $character->id,
                'actor_user_id' => $actor->id,
                'campaign_id' => $campaign->id,
                'scene_id' => $scene->id,
                'event_type' => CharacterProgressionEvent::EVENT_XP_MILESTONE,
                'xp_delta' => 1,
                'level_before' => 1,
                'level_after' => 1,
                'ap_delta' => 0,
                'attribute_deltas' => null,
                'reason' => 'Testlauf',
                'meta' => ['idx' => $index],
                'created_at' => now()->subMinutes(25 - $index),
            ]);
        }

        $expectedProgressionState = [
            'level' => 2,
            'xp_total' => 120,
            'xp_current_level_start' => 100,
            'xp_next_level_threshold' => 250,
            'xp_to_next_level' => 130,
            'progress_percent' => 13.33,
            'attribute_points_unspent' => 3,
        ];

        $progressionService = $this->createMock(CharacterProgressionService::class);
        $progressionService->expects($this->once())
            ->method('describe')
            ->with($this->callback(static fn (Character $resolvedCharacter): bool => $resolvedCharacter->is($character)))
            ->willReturn($expectedProgressionState);
        app()->instance(CharacterProgressionService::class, $progressionService);

        $result = app(BuildCharacterShowDataAction::class)->execute($character);

        $this->assertSame((int) $character->id, (int) $result->character->id);
        $this->assertCount(25, $result->inventoryLogs);
        $this->assertCount(20, $result->progressionEvents);
        $this->assertSame($expectedProgressionState, $result->progressionState);
        $this->assertTrue($result->inventoryLogs->first()?->relationLoaded('actor') ?? false);
        $this->assertTrue($result->progressionEvents->first()?->relationLoaded('actorUser') ?? false);
        $this->assertTrue($result->progressionEvents->first()?->relationLoaded('campaign') ?? false);
        $this->assertTrue($result->progressionEvents->first()?->relationLoaded('scene') ?? false);

        $latestInventoryLogId = (int) CharacterInventoryLog::query()
            ->where('character_id', $character->id)
            ->max('id');
        $latestProgressionEventId = (int) CharacterProgressionEvent::query()
            ->where('character_id', $character->id)
            ->max('id');

        $this->assertSame($latestInventoryLogId, (int) ($result->inventoryLogs->first()?->id ?? 0));
        $this->assertSame($latestProgressionEventId, (int) ($result->progressionEvents->first()?->id ?? 0));
    }
}
