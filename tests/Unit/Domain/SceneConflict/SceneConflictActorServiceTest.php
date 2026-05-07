<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\SceneConflict;

use App\Domain\SceneConflict\Exceptions\SceneConflictActorInvariantViolationException;
use App\Domain\SceneConflict\SceneConflictActorService;
use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Character;
use App\Models\Scene;
use App\Models\SceneConflictActor;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SceneConflictActorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_character_actor_for_scene(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantMembership($campaign, $participant, CampaignMembershipRole::PLAYER, $owner);
        $character = $this->characterInWorld($participant, (int) $campaign->world_id, [
            'name' => 'Vaelis',
            'le_max' => 34,
            'le_current' => 28,
            'ae_max' => 12,
            'ae_current' => 9,
        ]);

        $actor = $this->service()->addCharacterActor($campaign, $scene, $character);

        $this->assertSame(SceneConflictActor::TYPE_CHARACTER, (string) $actor->actor_type);
        $this->assertSame((int) $character->id, (int) $actor->character_id);
        $this->assertSame((int) $scene->id, (int) $actor->scene_id);
        $this->assertSame((int) $campaign->id, (int) $actor->campaign_id);
        $this->assertSame(1, (int) $actor->sort_order);
        $this->assertDatabaseCount('scene_conflict_actors', 1);
    }

    public function test_it_blocks_character_from_wrong_world(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantMembership($campaign, $participant, CampaignMembershipRole::PLAYER, $owner);

        $foreignWorld = World::factory()->create([
            'slug' => 'fremdwelt-conflict-actor',
            'is_active' => true,
            'position' => -400,
        ]);
        $character = $this->characterInWorld($participant, (int) $foreignWorld->id);

        $this->expectException(SceneConflictActorInvariantViolationException::class);

        try {
            $this->service()->addCharacterActor($campaign, $scene, $character);
        } catch (SceneConflictActorInvariantViolationException $exception) {
            $this->assertSame('character_world_mismatch', $exception->reason());
            throw $exception;
        }
    }

    public function test_it_blocks_character_that_is_not_campaign_participant(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $outsider = User::factory()->create();
        $character = $this->characterInWorld($outsider, (int) $campaign->world_id);

        $this->expectException(SceneConflictActorInvariantViolationException::class);

        try {
            $this->service()->addCharacterActor($campaign, $scene, $character);
        } catch (SceneConflictActorInvariantViolationException $exception) {
            $this->assertSame('character_not_participant', $exception->reason());
            throw $exception;
        }
    }

    public function test_it_blocks_duplicate_character_actor_in_same_scene(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantMembership($campaign, $participant, CampaignMembershipRole::PLAYER, $owner);
        $character = $this->characterInWorld($participant, (int) $campaign->world_id);

        $service = $this->service();
        $service->addCharacterActor($campaign, $scene, $character);

        $this->expectException(SceneConflictActorInvariantViolationException::class);

        try {
            $service->addCharacterActor($campaign, $scene, $character);
        } catch (SceneConflictActorInvariantViolationException $exception) {
            $this->assertSame('duplicate_character_actor', $exception->reason());
            throw $exception;
        }
    }

    public function test_it_adds_npc_actor_and_clamps_values(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();

        $actor = $this->service()->addNpcActor($campaign, $scene, [
            'name' => 'Hafenräuber I',
            'le_current' => 40,
            'le_max' => 20,
            'ae_current' => 50,
            'ae_max' => 30,
            'attack_value' => 150,
            'defense_value' => -10,
            'armor_protection' => -5,
            'damage_value' => 12,
            'spell_value' => 999,
        ]);

        $this->assertSame(SceneConflictActor::TYPE_NPC, (string) $actor->actor_type);
        $this->assertSame('Hafenräuber I', (string) $actor->name);
        $this->assertSame(20, (int) $actor->le_current);
        $this->assertSame(20, (int) $actor->le_max);
        $this->assertSame(30, (int) $actor->ae_current);
        $this->assertSame(30, (int) $actor->ae_max);
        $this->assertSame(100, (int) $actor->attack_value);
        $this->assertSame(0, (int) $actor->defense_value);
        $this->assertSame(0, (int) $actor->armor_protection);
        $this->assertSame(12, (int) $actor->damage_value);
        $this->assertSame(100, (int) $actor->spell_value);
    }

    public function test_it_keeps_null_fields_and_zero_values_for_npc_actor(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();

        $actor = $this->service()->addNpcActor($campaign, $scene, [
            'name' => 'Wache',
            'le_current' => null,
            'le_max' => null,
            'ae_current' => null,
            'ae_max' => null,
            'attack_value' => 0,
            'defense_value' => 0,
            'armor_protection' => 0,
            'damage_value' => 0,
            'spell_value' => 0,
        ]);

        $this->assertNull($actor->le_current);
        $this->assertNull($actor->le_max);
        $this->assertNull($actor->ae_current);
        $this->assertNull($actor->ae_max);
        $this->assertSame(0, (int) $actor->attack_value);
        $this->assertSame(0, (int) $actor->defense_value);
        $this->assertSame(0, (int) $actor->armor_protection);
        $this->assertSame(0, (int) $actor->damage_value);
        $this->assertSame(0, (int) $actor->spell_value);

        $actorWithCurrentOnly = $this->service()->addNpcActor($campaign, $scene, [
            'name' => 'Späher',
            'le_current' => 7,
            'le_max' => null,
            'ae_current' => 3,
            'ae_max' => null,
        ]);

        $this->assertSame(7, (int) $actorWithCurrentOnly->le_current);
        $this->assertNull($actorWithCurrentOnly->le_max);
        $this->assertSame(3, (int) $actorWithCurrentOnly->ae_current);
        $this->assertNull($actorWithCurrentOnly->ae_max);
    }

    public function test_it_updates_npc_actor_with_clamping(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();

        $actor = SceneConflictActor::query()->create([
            'campaign_id' => (int) $campaign->id,
            'scene_id' => (int) $scene->id,
            'actor_type' => SceneConflictActor::TYPE_NPC,
            'name' => 'Tempelhexe',
            'le_current' => 18,
            'le_max' => 18,
            'ae_current' => 24,
            'ae_max' => 24,
            'attack_value' => 35,
            'defense_value' => 30,
            'armor_protection' => 1,
            'damage_value' => 6,
            'spell_value' => 55,
            'sort_order' => 3,
        ]);

        $updated = $this->service()->updateNpcActor($actor, [
            'name' => 'Tempelhexe Elite',
            'le_current' => 25,
            'le_max' => 19,
            'attack_value' => 140,
            'defense_value' => 41,
            'armor_protection' => 2,
            'damage_value' => 9,
            'spell_value' => 101,
            'notes' => 'Fokussiert auf Flüche',
            'sort_order' => 2,
        ]);

        $this->assertSame('Tempelhexe Elite', (string) $updated->name);
        $this->assertSame(19, (int) $updated->le_current);
        $this->assertSame(19, (int) $updated->le_max);
        $this->assertSame(100, (int) $updated->attack_value);
        $this->assertSame(41, (int) $updated->defense_value);
        $this->assertSame(2, (int) $updated->armor_protection);
        $this->assertSame(9, (int) $updated->damage_value);
        $this->assertSame(100, (int) $updated->spell_value);
        $this->assertSame('Fokussiert auf Flüche', (string) $updated->notes);
        $this->assertSame(2, (int) $updated->sort_order);
    }

    public function test_it_rejects_npc_update_for_character_actor(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantMembership($campaign, $participant, CampaignMembershipRole::PLAYER, $owner);
        $character = $this->characterInWorld($participant, (int) $campaign->world_id, ['name' => 'Mara']);

        $actor = SceneConflictActor::query()->create([
            'campaign_id' => (int) $campaign->id,
            'scene_id' => (int) $scene->id,
            'actor_type' => SceneConflictActor::TYPE_CHARACTER,
            'character_id' => (int) $character->id,
            'name' => 'Mara',
        ]);

        $this->expectException(SceneConflictActorInvariantViolationException::class);

        try {
            $this->service()->updateNpcActor($actor, [
                'name' => 'Mara geändert',
            ]);
        } catch (SceneConflictActorInvariantViolationException $exception) {
            $this->assertSame('npc_update_only', $exception->reason());
            throw $exception;
        }
    }

    public function test_it_removes_actor_without_side_effects(): void
    {
        [$owner, $campaign, $scene] = $this->campaignContext();

        $actor = SceneConflictActor::query()->create([
            'campaign_id' => (int) $campaign->id,
            'scene_id' => (int) $scene->id,
            'actor_type' => SceneConflictActor::TYPE_NPC,
            'name' => 'Hafenräuber II',
        ]);

        $this->service()->removeActor($actor);

        $this->assertDatabaseCount('scene_conflict_actors', 0);
    }

    private function service(): SceneConflictActorService
    {
        return app(SceneConflictActorService::class);
    }

    /**
     * @return array{0: User, 1: Campaign, 2: Scene}
     */
    private function campaignContext(): array
    {
        $owner = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        return [$owner, $campaign, $scene];
    }

    private function grantMembership(Campaign $campaign, User $user, CampaignMembershipRole $role, User $inviter): void
    {
        CampaignMembership::query()->updateOrCreate(
            [
                'campaign_id' => (int) $campaign->id,
                'user_id' => (int) $user->id,
            ],
            [
                'role' => $role->value,
                'assigned_by' => (int) $inviter->id,
                'assigned_at' => now(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function characterInWorld(User $user, int $worldId, array $overrides = []): Character
    {
        return Character::factory()->create(array_merge([
            'user_id' => $user->id,
            'world_id' => $worldId,
            'origin' => 'native_vhaltor',
            'species' => 'mensch',
            'calling' => 'abenteurer',
            'name' => 'Charakter',
            'mu' => 40,
            'kl' => 40,
            'in' => 40,
            'ch' => 40,
            'ff' => 40,
            'ge' => 40,
            'ko' => 40,
            'kk' => 40,
            'strength' => 40,
            'dexterity' => 40,
            'constitution' => 40,
            'intelligence' => 40,
            'wisdom' => 40,
            'charisma' => 40,
            'le_max' => 20,
            'le_current' => 20,
            'ae_max' => 0,
            'ae_current' => 0,
            'armors' => [],
        ], $overrides));
    }
}
