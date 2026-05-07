<?php

declare(strict_types=1);

namespace Tests\Feature;

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

class SceneConflictActorTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_flag_off_hides_panel_and_blocks_routes(): void
    {
        config(['features.combat_tools_enabled' => false]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $actor = SceneConflictActor::query()->create([
            'campaign_id' => (int) $campaign->id,
            'scene_id' => (int) $scene->id,
            'actor_type' => SceneConflictActor::TYPE_NPC,
            'name' => 'Hafenräuber I',
        ]);

        $this->actingAs($owner)
            ->get(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertOk()
            ->assertDontSeeText('Beteiligte (Spielleitung)');

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.conflict-actors.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]), [
                'actor_type' => 'npc',
                'name' => 'Hafenräuber II',
            ])
            ->assertNotFound();

        $this->actingAs($owner)
            ->patch(route('campaigns.scenes.conflict-actors.update', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene, 'sceneConflictActor' => $actor]), [
                'name' => 'Neu',
            ])
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete(route('campaigns.scenes.conflict-actors.destroy', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene, 'sceneConflictActor' => $actor]))
            ->assertNotFound();
    }

    public function test_spielleitung_sees_panel_and_player_does_not(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $player = User::factory()->create();
        $this->grantMembership($campaign, $player, CampaignMembershipRole::PLAYER, $owner);

        $this->actingAs($owner)
            ->get(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertOk()
            ->assertSeeText('Beteiligte (Spielleitung)')
            ->assertSeeText('Diese Beteiligten können in Kampf- und Magieformularen ausgewählt werden.');

        $this->actingAs($player)
            ->get(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertOk()
            ->assertDontSeeText('Beteiligte (Spielleitung)');
    }

    public function test_spielleitung_can_add_character_actor(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $participant = User::factory()->create();
        $this->grantMembership($campaign, $participant, CampaignMembershipRole::PLAYER, $owner);
        $character = $this->characterInCampaignWorld($participant, (int) $campaign->world_id, [
            'name' => 'Vaelis',
            'le_max' => 34,
            'le_current' => 28,
        ]);

        $response = $this->actingAs($owner)->post(
            route('campaigns.scenes.conflict-actors.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]),
            [
                'actor_type' => 'character',
                'character_id' => (int) $character->id,
            ],
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('scene_conflict_actors', [
            'scene_id' => (int) $scene->id,
            'actor_type' => SceneConflictActor::TYPE_CHARACTER,
            'character_id' => (int) $character->id,
            'name' => 'Vaelis',
        ]);

        $this->actingAs($owner)
            ->get(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertOk()
            ->assertSeeText('Vaelis');
    }

    public function test_spielleitung_can_add_and_update_npc_actor(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();

        $this->actingAs($owner)->post(
            route('campaigns.scenes.conflict-actors.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]),
            [
                'actor_type' => 'npc',
                'name' => 'Hafenräuber I',
                'le_current' => 22,
                'le_max' => 20,
                'attack_value' => 50,
                'defense_value' => 35,
                'armor_protection' => 2,
                'damage_value' => 8,
            ],
        )->assertRedirect();

        $actor = SceneConflictActor::query()->where('scene_id', (int) $scene->id)->firstOrFail();
        $this->assertSame(20, (int) $actor->le_current);
        $this->assertSame(20, (int) $actor->le_max);

        $this->actingAs($owner)->patch(
            route('campaigns.scenes.conflict-actors.update', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene, 'sceneConflictActor' => $actor]),
            [
                'name' => 'Hafenräuber Veteran',
                'le_current' => 19,
                'le_max' => 21,
                'attack_value' => 57,
                'defense_value' => 39,
                'armor_protection' => 3,
                'damage_value' => 10,
                'spell_value' => 12,
            ],
        )->assertRedirect();

        $actor->refresh();
        $this->assertSame('Hafenräuber Veteran', (string) $actor->name);
        $this->assertSame(19, (int) $actor->le_current);
        $this->assertSame(21, (int) $actor->le_max);
        $this->assertSame(57, (int) $actor->attack_value);
        $this->assertSame(39, (int) $actor->defense_value);
        $this->assertSame(3, (int) $actor->armor_protection);
        $this->assertSame(10, (int) $actor->damage_value);
        $this->assertSame(12, (int) $actor->spell_value);
    }

    public function test_spielleitung_can_remove_actor(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $actor = SceneConflictActor::query()->create([
            'campaign_id' => (int) $campaign->id,
            'scene_id' => (int) $scene->id,
            'actor_type' => SceneConflictActor::TYPE_NPC,
            'name' => 'Hafenräuber II',
        ]);

        $this->actingAs($owner)->delete(
            route('campaigns.scenes.conflict-actors.destroy', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene, 'sceneConflictActor' => $actor]),
        )->assertRedirect();

        $this->assertDatabaseCount('scene_conflict_actors', 0);
    }

    public function test_player_cannot_manage_scene_conflict_actors(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $player = User::factory()->create();
        $this->grantMembership($campaign, $player, CampaignMembershipRole::PLAYER, $owner);
        $actor = SceneConflictActor::query()->create([
            'campaign_id' => (int) $campaign->id,
            'scene_id' => (int) $scene->id,
            'actor_type' => SceneConflictActor::TYPE_NPC,
            'name' => 'Bandit',
        ]);

        $this->actingAs($player)->post(
            route('campaigns.scenes.conflict-actors.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]),
            ['actor_type' => 'npc', 'name' => 'Wache'],
        )->assertForbidden();

        $this->actingAs($player)->patch(
            route('campaigns.scenes.conflict-actors.update', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene, 'sceneConflictActor' => $actor]),
            ['name' => 'Neu'],
        )->assertForbidden();

        $this->actingAs($player)->delete(
            route('campaigns.scenes.conflict-actors.destroy', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene, 'sceneConflictActor' => $actor]),
        )->assertForbidden();
    }

    public function test_outsider_and_guest_cannot_manage_scene_conflict_actors(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->post(
            route('campaigns.scenes.conflict-actors.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]),
            ['actor_type' => 'npc', 'name' => 'Wache'],
        )->assertForbidden();

        $this->post(
            route('campaigns.scenes.conflict-actors.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]),
            ['actor_type' => 'npc', 'name' => 'Wache'],
        )->assertForbidden();
    }

    public function test_panel_uses_unique_prefixed_ids_for_add_and_npc_update_forms(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $npcA = SceneConflictActor::query()->create([
            'campaign_id' => (int) $campaign->id,
            'scene_id' => (int) $scene->id,
            'actor_type' => SceneConflictActor::TYPE_NPC,
            'name' => 'Hafenräuber I',
        ]);
        $npcB = SceneConflictActor::query()->create([
            'campaign_id' => (int) $campaign->id,
            'scene_id' => (int) $scene->id,
            'actor_type' => SceneConflictActor::TYPE_NPC,
            'name' => 'Hafenräuber II',
        ]);

        $response = $this->actingAs($owner)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $response->assertOk()
            ->assertSee('id="conflict_add_character_character_id"', false)
            ->assertSee('id="conflict_add_npc_name"', false)
            ->assertSee('id="conflict_actor_'.$npcA->id.'_name"', false)
            ->assertSee('id="conflict_actor_'.$npcB->id.'_name"', false)
            ->assertDontSee('id="conflict_actor_npc_name"', false);
    }

    public function test_world_campaign_scene_guard_blocks_foreign_world_context(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $foreignWorld = World::factory()->create([
            'slug' => 'fremdwelt-conflict-actors',
            'is_active' => true,
            'position' => -450,
        ]);

        $this->actingAs($owner)->post(
            route('campaigns.scenes.conflict-actors.store', ['world' => $foreignWorld, 'campaign' => $campaign, 'scene' => $scene]),
            ['actor_type' => 'npc', 'name' => 'Wache'],
        )->assertNotFound();
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
    private function characterInCampaignWorld(User $user, int $worldId, array $overrides = []): Character
    {
        return Character::factory()->create(array_merge([
            'user_id' => $user->id,
            'world_id' => $worldId,
            'origin' => 'native_vhaltor',
            'species' => 'mensch',
            'calling' => 'abenteurer',
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
