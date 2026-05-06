<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use App\Support\ProbeRoller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SceneCombatActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_flag_off_hides_ui_and_post_route_returns_404(): void
    {
        config(['features.combat_tools_enabled' => false]);

        [$owner, $campaign, $scene] = $this->campaignContext();

        $this->actingAs($owner)
            ->get(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertOk()
            ->assertDontSeeText('Kampfaktion (Spielleitung)');

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.combat.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]), $this->combatPayload())
            ->assertNotFound();
    }

    public function test_spielleitung_can_resolve_character_vs_character_action_and_store_combat_block(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $attackerUser = User::factory()->create();
        $targetUser = User::factory()->create();
        $this->grantMembership($campaign, $attackerUser, CampaignMembershipRole::PLAYER, $owner);
        $this->grantMembership($campaign, $targetUser, CampaignMembershipRole::PLAYER, $owner);

        $actorCharacter = $this->characterInCampaignWorld($attackerUser, (int) $campaign->world_id, [
            'name' => 'Vaelis',
        ]);
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

        $this->bindProbeRoller([43, 71]);

        $response = $this->actingAs($owner)->post(
            route('campaigns.scenes.combat.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]),
            $this->combatPayload([
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
            ]),
        );

        $response->assertRedirect();
        $this->assertStringContainsString('#post-', (string) $response->headers->get('Location'));

        $targetCharacter->refresh();
        $this->assertSame(24, (int) $targetCharacter->le_current);

        /** @var Post $combatPost */
        $combatPost = Post::query()->latest('id')->firstOrFail();
        $this->assertSame((int) $scene->id, (int) $combatPost->scene_id);
        $this->assertSame((int) $owner->id, (int) $combatPost->user_id);
        $this->assertNull($combatPost->character_id);
        $this->assertSame('ic', $combatPost->post_type);
        $this->assertTrue($combatPost->isGmNarration());
        $this->assertDatabaseCount('dice_rolls', 0);

        $showResponse = $this->actingAs($owner)->get(route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]));

        $showResponse->assertOk()
            ->assertSeeText('Kampfaktion')
            ->assertSeeText('Angreifer: Vaelis')
            ->assertSeeText('Ziel: Hafenwaechter')
            ->assertSeeText('Schaden: 12 - RS 3 = 9')
            ->assertSeeText('LE: 24 / 33');
    }

    public function test_player_without_sl_rights_cannot_use_combat_action_tool(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $player = User::factory()->create();
        $this->grantMembership($campaign, $player, CampaignMembershipRole::PLAYER, $owner);

        $this->actingAs($player)
            ->get(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertOk()
            ->assertDontSeeText('Kampfaktion (Spielleitung)');

        $response = $this->actingAs($player)->post(
            route('campaigns.scenes.combat.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]),
            $this->combatPayload([
                'actor_type' => 'npc',
                'actor_name' => 'Bandit',
                'target_type' => 'npc',
                'target_name' => 'Wache',
            ]),
        );

        $response->assertForbidden();
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_spielleitung_can_resolve_character_vs_npc_without_mutating_character_state(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $attackerUser = User::factory()->create();
        $this->grantMembership($campaign, $attackerUser, CampaignMembershipRole::PLAYER, $owner);

        $actorCharacter = $this->characterInCampaignWorld($attackerUser, (int) $campaign->world_id, [
            'name' => 'Kara',
        ]);
        $unchangedCharacter = $this->characterInCampaignWorld($attackerUser, (int) $campaign->world_id, [
            'name' => 'Unbeteiligter',
            'le_max' => 19,
            'le_current' => 19,
        ]);

        $this->bindProbeRoller([20]);

        $response = $this->actingAs($owner)->post(
            route('campaigns.scenes.combat.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]),
            $this->combatPayload([
                'actor_type' => 'character',
                'actor_character_id' => $actorCharacter->id,
                'target_type' => 'npc',
                'target_name' => 'Hafenraeuber I',
                'target_le_current' => 18,
                'target_le_max' => 20,
                'damage' => 7,
                'armor_protection' => 2,
            ]),
        );

        $response->assertRedirect();
        $this->assertStringContainsString('#post-', (string) $response->headers->get('Location'));

        $unchangedCharacter->refresh();
        $this->assertSame(19, (int) $unchangedCharacter->le_current);

        $this->actingAs($owner)
            ->get(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertOk()
            ->assertSeeText('Ziel: Hafenraeuber I')
            ->assertSeeText('Schaden: 7 - RS 2 = 5')
            ->assertSeeText('LE: 13 / 20');
    }

    public function test_defense_success_is_rendered_and_le_stays_unchanged(): void
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

        $this->actingAs($owner)->post(
            route('campaigns.scenes.combat.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]),
            $this->combatPayload([
                'actor_type' => 'character',
                'actor_character_id' => $actorCharacter->id,
                'target_type' => 'character',
                'target_character_id' => $targetCharacter->id,
                'attack_target_value' => 60,
                'defense_label' => 'Parade',
                'defense_target_value' => 45,
                'damage' => 12,
            ]),
        )->assertRedirect();

        $targetCharacter->refresh();
        $this->assertSame(22, (int) $targetCharacter->le_current);

        $this->actingAs($owner)
            ->get(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertOk()
            ->assertSeeText('Ergebnis: Der Treffer wird abgewehrt. Kein Schaden.')
            ->assertSeeText('LE: 22 / 22');
    }

    public function test_validation_errors_redirect_back_to_combat_form_without_state_changes(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();

        $response = $this->actingAs($owner)
            ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->post(route('campaigns.scenes.combat.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]), [
                'actor_type' => 'npc',
                'actor_name' => '',
                'target_type' => 'npc',
                'target_name' => 'Hafenraeuber I',
                'damage' => 5,
            ]);

        $response->assertRedirect(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]));
        $response->assertSessionHasErrors(['actor_name', 'attack_target_value']);
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_world_campaign_scene_guard_rejects_foreign_world_route_context(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $foreignWorld = World::factory()->create([
            'slug' => 'combat-fremdwelt',
            'is_active' => true,
            'position' => -450,
        ]);

        $this->actingAs($owner)->post(
            route('campaigns.scenes.combat.actions.store', ['world' => $foreignWorld, 'campaign' => $campaign, 'scene' => $scene]),
            $this->combatPayload([
                'actor_type' => 'npc',
                'actor_name' => 'Bandit',
                'target_type' => 'npc',
                'target_name' => 'Wache',
            ]),
        )->assertNotFound();
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
