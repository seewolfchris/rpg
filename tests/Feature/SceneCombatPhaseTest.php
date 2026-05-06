<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Character;
use App\Models\CombatPhase;
use App\Models\CombatPhaseAction;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use App\Support\ProbeRoller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SceneCombatPhaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_flag_off_hides_phase_ui_and_blocks_phase_routes(): void
    {
        config(['features.combat_tools_enabled' => false]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $phase = $this->openPhase($campaign, $scene, $owner);

        $this->actingAs($owner)
            ->get(route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->assertOk()
            ->assertDontSeeText('Kampfphase (Spielleitung)');

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.actions.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
                'combatPhase' => $phase,
            ]), $this->combatPayload())
            ->assertNotFound();

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.resolve', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
                'combatPhase' => $phase,
            ]))
            ->assertNotFound();

        $phase->refresh();
        $this->assertSame(CombatPhase::STATUS_COLLECTING, (string) $phase->status);
        $this->assertDatabaseCount('combat_phase_actions', 0);
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_spielleitung_can_start_queue_and_resolve_phase_with_gm_combat_block(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $attackerUser = User::factory()->create();
        $targetUser = User::factory()->create();
        $this->grantMembership($campaign, $attackerUser, CampaignMembershipRole::PLAYER, $owner);
        $this->grantMembership($campaign, $targetUser, CampaignMembershipRole::PLAYER, $owner);

        $actorCharacter = $this->characterInCampaignWorld($attackerUser, (int) $campaign->world_id, ['name' => 'Vaelis']);
        $targetCharacter = $this->characterInCampaignWorld($targetUser, (int) $campaign->world_id, [
            'name' => 'Hafenwaechter',
            'le_max' => 33,
            'le_current' => 33,
            'armors' => [[
                'name' => 'Lederwams',
                'protection' => 3,
                'equipped' => true,
            ]],
        ]);

        $this->bindProbeRoller([43, 71, 25]);

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->assertRedirect();

        $phase = CombatPhase::query()
            ->where('scene_id', (int) $scene->id)
            ->where('status', CombatPhase::STATUS_COLLECTING)
            ->firstOrFail();

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.actions.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
                'combatPhase' => $phase,
            ]), $this->combatPayload([
                'actor_type' => 'character',
                'actor_character_id' => $actorCharacter->id,
                'target_type' => 'character',
                'target_character_id' => $targetCharacter->id,
                'weapon_name' => 'Langschwert',
                'attack_target_value' => 60,
                'defense_label' => 'Parade',
                'defense_target_value' => 45,
                'damage' => 12,
                'armor_protection' => null,
                'intent_text' => 'Stoß gegen die Hafenkante.',
                'resolution_note' => 'Erster sauberer Treffer.',
            ]))
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.actions.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
                'combatPhase' => $phase,
            ]), $this->combatPayload([
                'actor_type' => 'character',
                'actor_character_id' => $actorCharacter->id,
                'target_type' => 'npc',
                'target_name' => 'Hafenraeuber I',
                'target_le_current' => 18,
                'target_le_max' => 20,
                'damage' => 7,
                'armor_protection' => 2,
            ]))
            ->assertRedirect();

        $targetCharacter->refresh();
        $this->assertSame(33, (int) $targetCharacter->le_current);
        $this->assertDatabaseCount('combat_phase_actions', 2);

        $resolveResponse = $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.resolve', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
                'combatPhase' => $phase,
            ]));

        $resolveResponse->assertRedirect();
        $this->assertStringContainsString('#post-', (string) $resolveResponse->headers->get('Location'));

        $phase->refresh();
        $targetCharacter->refresh();

        $this->assertSame(CombatPhase::STATUS_RESOLVED, (string) $phase->status);
        $this->assertNotNull($phase->resolved_at);
        $this->assertSame((int) $owner->id, (int) $phase->resolved_by);
        $this->assertIsArray($phase->resolution_summary);
        $this->assertSame(24, (int) $targetCharacter->le_current);

        $storedActions = CombatPhaseAction::query()
            ->where('combat_phase_id', (int) $phase->id)
            ->orderBy('position')
            ->get();
        $this->assertCount(2, $storedActions);
        $this->assertSame(1, (int) $storedActions[0]->position);
        $this->assertSame(2, (int) $storedActions[1]->position);
        $this->assertNotNull($storedActions[0]->result);
        $this->assertNotNull($storedActions[1]->result);

        /** @var Post $combatPost */
        $combatPost = Post::query()->latest('id')->firstOrFail();
        $this->assertTrue($combatPost->isGmNarration());
        $this->assertStringContainsString('[Kampfphase 1]', (string) $combatPost->content);
        $this->assertStringContainsString('Kampfphase 1 ausgewertet', (string) $combatPost->content);
        $this->assertStringContainsString('Vaelis -> Hafenwaechter', (string) $combatPost->content);
        $this->assertStringContainsString('LE: 24 / 33', (string) $combatPost->content);
        $this->assertSame(2, (int) data_get($combatPost->meta, 'combat_phase_result.action_count'));
    }

    public function test_player_cannot_see_or_use_combat_phase_tool(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $player = User::factory()->create();
        $this->grantMembership($campaign, $player, CampaignMembershipRole::PLAYER, $owner);

        $phase = $this->openPhase($campaign, $scene, $owner);

        $this->actingAs($player)
            ->get(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertOk()
            ->assertDontSeeText('Kampfphase (Spielleitung)');

        $this->actingAs($player)
            ->post(route('campaigns.scenes.combat.phases.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->assertForbidden();

        $this->actingAs($player)
            ->post(route('campaigns.scenes.combat.phases.actions.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
                'combatPhase' => $phase,
            ]), $this->combatPayload())
            ->assertForbidden();

        $this->actingAs($player)
            ->post(route('campaigns.scenes.combat.phases.resolve', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
                'combatPhase' => $phase,
            ]))
            ->assertForbidden();

        $phase->refresh();
        $this->assertSame(CombatPhase::STATUS_COLLECTING, (string) $phase->status);
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_resolving_npc_target_in_phase_does_not_mutate_unrelated_character(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $attackerUser = User::factory()->create();
        $participantUser = User::factory()->create();
        $this->grantMembership($campaign, $attackerUser, CampaignMembershipRole::PLAYER, $owner);
        $this->grantMembership($campaign, $participantUser, CampaignMembershipRole::PLAYER, $owner);

        $actorCharacter = $this->characterInCampaignWorld($attackerUser, (int) $campaign->world_id, ['name' => 'Kara']);
        $unchangedCharacter = $this->characterInCampaignWorld($participantUser, (int) $campaign->world_id, [
            'name' => 'Unbeteiligter',
            'le_max' => 19,
            'le_current' => 19,
        ]);

        $this->bindProbeRoller([20]);

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->assertRedirect();

        $phase = CombatPhase::query()->where('scene_id', (int) $scene->id)->where('status', CombatPhase::STATUS_COLLECTING)->firstOrFail();

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.actions.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
                'combatPhase' => $phase,
            ]), $this->combatPayload([
                'actor_type' => 'character',
                'actor_character_id' => $actorCharacter->id,
                'target_type' => 'npc',
                'target_name' => 'Hafenraeuber I',
                'target_le_current' => 18,
                'target_le_max' => 20,
                'damage' => 7,
                'armor_protection' => 2,
            ]))
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.resolve', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
                'combatPhase' => $phase,
            ]))
            ->assertRedirect();

        $unchangedCharacter->refresh();
        $this->assertSame(19, (int) $unchangedCharacter->le_current);

        $action = CombatPhaseAction::query()->where('combat_phase_id', (int) $phase->id)->firstOrFail();
        $this->assertSame('npc', (string) data_get($action->result, 'target.type'));
        $this->assertSame(5, (int) data_get($action->result, 'outcome.effective_damage'));
    }

    public function test_defense_success_in_phase_prevents_damage(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $attackerUser = User::factory()->create();
        $targetUser = User::factory()->create();
        $this->grantMembership($campaign, $attackerUser, CampaignMembershipRole::PLAYER, $owner);
        $this->grantMembership($campaign, $targetUser, CampaignMembershipRole::PLAYER, $owner);

        $actorCharacter = $this->characterInCampaignWorld($attackerUser, (int) $campaign->world_id, ['name' => 'Orin']);
        $targetCharacter = $this->characterInCampaignWorld($targetUser, (int) $campaign->world_id, [
            'name' => 'Mira',
            'le_max' => 22,
            'le_current' => 22,
        ]);

        $this->bindProbeRoller([31, 22]);

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->assertRedirect();

        $phase = CombatPhase::query()->where('scene_id', (int) $scene->id)->where('status', CombatPhase::STATUS_COLLECTING)->firstOrFail();

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.actions.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
                'combatPhase' => $phase,
            ]), $this->combatPayload([
                'actor_type' => 'character',
                'actor_character_id' => $actorCharacter->id,
                'target_type' => 'character',
                'target_character_id' => $targetCharacter->id,
                'attack_target_value' => 60,
                'defense_label' => 'Parade',
                'defense_target_value' => 45,
                'damage' => 12,
            ]))
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.resolve', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
                'combatPhase' => $phase,
            ]))
            ->assertRedirect();

        $targetCharacter->refresh();
        $this->assertSame(22, (int) $targetCharacter->le_current);

        $combatPost = Post::query()->latest('id')->firstOrFail();
        $this->assertStringContainsString('Der Treffer wird abgewehrt. Kein Schaden.', (string) $combatPost->content);
    }

    public function test_resolve_without_actions_returns_error_and_phase_stays_collecting(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->assertRedirect();

        $phase = CombatPhase::query()
            ->where('scene_id', (int) $scene->id)
            ->where('status', CombatPhase::STATUS_COLLECTING)
            ->firstOrFail();

        $response = $this->actingAs($owner)
            ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->post(route('campaigns.scenes.combat.phases.resolve', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
                'combatPhase' => $phase,
            ]));

        $response->assertRedirect(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]).'#combat-phase-tool');
        $response->assertSessionHasErrors(['combat_phase']);

        $phase->refresh();
        $this->assertSame(CombatPhase::STATUS_COLLECTING, (string) $phase->status);
        $this->assertNull($phase->resolved_at);
        $this->assertNull($phase->resolution_summary);
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_second_resolve_is_blocked_and_does_not_create_duplicate_post_or_le_change(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $attackerUser = User::factory()->create();
        $targetUser = User::factory()->create();
        $this->grantMembership($campaign, $attackerUser, CampaignMembershipRole::PLAYER, $owner);
        $this->grantMembership($campaign, $targetUser, CampaignMembershipRole::PLAYER, $owner);

        $actorCharacter = $this->characterInCampaignWorld($attackerUser, (int) $campaign->world_id, ['name' => 'Orin']);
        $targetCharacter = $this->characterInCampaignWorld($targetUser, (int) $campaign->world_id, [
            'name' => 'Mira',
            'le_max' => 22,
            'le_current' => 22,
            'armors' => [],
        ]);

        $this->bindProbeRoller([20]);

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->assertRedirect();

        $phase = CombatPhase::query()
            ->where('scene_id', (int) $scene->id)
            ->where('status', CombatPhase::STATUS_COLLECTING)
            ->firstOrFail();

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.actions.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
                'combatPhase' => $phase,
            ]), $this->combatPayload([
                'actor_type' => 'character',
                'actor_character_id' => $actorCharacter->id,
                'target_type' => 'character',
                'target_character_id' => $targetCharacter->id,
                'damage' => 7,
                'armor_protection' => 0,
            ]))
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.resolve', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
                'combatPhase' => $phase,
            ]))
            ->assertRedirect();

        $targetCharacter->refresh();
        $firstResolvedLe = (int) $targetCharacter->le_current;
        $this->assertSame(15, $firstResolvedLe);
        $this->assertDatabaseCount('posts', 1);

        $secondResponse = $this->actingAs($owner)
            ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->post(route('campaigns.scenes.combat.phases.resolve', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
                'combatPhase' => $phase->fresh(),
            ]));

        $secondResponse->assertRedirect(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]).'#combat-phase-tool');
        $secondResponse->assertSessionHasErrors(['combat_phase']);

        $targetCharacter->refresh();
        $this->assertSame($firstResolvedLe, (int) $targetCharacter->le_current);
        $this->assertDatabaseCount('posts', 1);
    }

    public function test_phase_action_validation_error_keeps_state_unchanged(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->assertRedirect();

        $phase = CombatPhase::query()->where('scene_id', (int) $scene->id)->where('status', CombatPhase::STATUS_COLLECTING)->firstOrFail();

        $response = $this->actingAs($owner)
            ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->post(route('campaigns.scenes.combat.phases.actions.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
                'combatPhase' => $phase,
            ]), [
                'actor_type' => 'npc',
                'actor_name' => '',
                'target_type' => 'npc',
                'target_name' => 'Wache',
                'damage' => 5,
            ]);

        $response->assertRedirect(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]));
        $response->assertSessionHasErrors(['actor_name', 'attack_target_value']);

        $this->assertDatabaseCount('combat_phase_actions', 0);
        $this->assertSame(CombatPhase::STATUS_COLLECTING, (string) $phase->fresh()->status);
    }

    public function test_world_campaign_scene_phase_guards_block_foreign_contexts(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $foreignWorld = World::factory()->create([
            'slug' => 'combat-phase-fremdwelt',
            'is_active' => true,
            'position' => -420,
        ]);

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.store', [
                'world' => $foreignWorld,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->assertNotFound();

        $phase = $this->openPhase($campaign, $scene, $owner);
        $otherScene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.phases.actions.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $otherScene,
                'combatPhase' => $phase,
            ]), $this->combatPayload())
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function combatPayload(array $overrides = []): array
    {
        return array_merge([
            'actor_type' => 'npc',
            'actor_name' => 'Bandit',
            'target_type' => 'npc',
            'target_name' => 'Wache',
            'attack_target_value' => 60,
            'attack_roll_mode' => 'normal',
            'attack_modifier' => 0,
            'defense_label' => null,
            'defense_target_value' => null,
            'defense_roll_mode' => 'normal',
            'defense_modifier' => 0,
            'damage' => 5,
            'armor_protection' => 0,
            'intent_text' => null,
            'resolution_note' => null,
        ], $overrides);
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

    private function openPhase(Campaign $campaign, Scene $scene, User $owner): CombatPhase
    {
        return CombatPhase::query()->create([
            'campaign_id' => (int) $campaign->id,
            'scene_id' => (int) $scene->id,
            'phase_number' => 1,
            'status' => CombatPhase::STATUS_COLLECTING,
            'started_by' => (int) $owner->id,
            'resolved_by' => null,
            'resolved_at' => null,
            'resolution_summary' => null,
        ]);
    }

    /**
     * @param  list<int>  $rolls
     */
    private function bindProbeRoller(array $rolls): void
    {
        $this->app->instance(ProbeRoller::class, new ProbeRoller(static function () use (&$rolls): int {
            $next = array_shift($rolls);

            return is_int($next) ? $next : 50;
        }));
    }
}
