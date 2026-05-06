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

class SceneMagicActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_flag_off_hides_ui_and_magic_post_route_returns_404(): void
    {
        config(['features.combat_tools_enabled' => false]);

        [$owner, $campaign, $scene] = $this->campaignContext();

        $this->actingAs($owner)
            ->get(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertOk()
            ->assertDontSeeText('Magieaktion (Spielleitung)');

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.magic.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]), $this->magicPayload())
            ->assertNotFound();

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_spielleitung_sees_magic_ui_but_player_does_not(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $player = User::factory()->create();
        $this->grantMembership($campaign, $player, CampaignMembershipRole::PLAYER, $owner);

        $this->actingAs($owner)
            ->get(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertOk()
            ->assertSeeText('Magieaktion (Spielleitung)');

        $this->actingAs($player)
            ->get(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertOk()
            ->assertDontSeeText('Magieaktion (Spielleitung)');
    }

    public function test_spielleitung_can_resolve_character_vs_character_magic_action_and_store_magic_block(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $casterUser = User::factory()->create();
        $targetUser = User::factory()->create();
        $this->grantMembership($campaign, $casterUser, CampaignMembershipRole::PLAYER, $owner);
        $this->grantMembership($campaign, $targetUser, CampaignMembershipRole::PLAYER, $owner);

        $casterCharacter = $this->characterInCampaignWorld($casterUser, (int) $campaign->world_id, [
            'name' => 'Eldrin',
            'ae_max' => 12,
            'ae_current' => 10,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($targetUser, (int) $campaign->world_id, [
            'name' => 'Hafenräuber I',
            'le_max' => 30,
            'le_current' => 30,
        ]);

        $this->bindProbeRoller([31, 71]);

        $response = $this->actingAs($owner)->post(
            route('campaigns.scenes.magic.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]),
            $this->magicPayload([
                'actor_type' => 'character',
                'actor_character_id' => $casterCharacter->id,
                'target_type' => 'character',
                'target_character_id' => $targetCharacter->id,
                'spell_name' => 'Flammenstoß',
                'spell_target_value' => 60,
                'ae_cost' => 4,
                'defense_label' => 'Magieabwehr',
                'defense_target_value' => 45,
                'effect_type' => 'le_damage',
                'effect_amount' => 9,
                'intent_text' => 'Ein konzentrierter Flammenkegel.',
                'resolution_note' => 'Trifft den Räuber mittig.',
            ]),
        );

        $response->assertRedirect();
        $this->assertStringContainsString('#post-', (string) $response->headers->get('Location'));

        $casterCharacter->refresh();
        $targetCharacter->refresh();
        $this->assertSame(6, (int) $casterCharacter->ae_current);
        $this->assertSame(21, (int) $targetCharacter->le_current);

        /** @var Post $magicPost */
        $magicPost = Post::query()->latest('id')->firstOrFail();
        $this->assertTrue($magicPost->isGmNarration());
        $this->assertSame(9, (int) data_get($magicPost->meta, 'magic_result.effect.effect_amount'));

        $this->actingAs($owner)
            ->get(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->assertOk()
            ->assertSeeText('Magieaktion')
            ->assertSeeText('Zaubernder: Eldrin')
            ->assertSeeText('Ziel: Hafenräuber I')
            ->assertSeeText('Zauber: Flammenstoß')
            ->assertSeeText('Kosten: 4 AE')
            ->assertSeeText('Wirkung: 9 LE Schaden')
            ->assertSeeText('LE: 21 / 30');
    }

    public function test_player_cannot_resolve_magic_action(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $player = User::factory()->create();
        $this->grantMembership($campaign, $player, CampaignMembershipRole::PLAYER, $owner);

        $response = $this->actingAs($player)->post(
            route('campaigns.scenes.magic.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]),
            $this->magicPayload(),
        );

        $response->assertForbidden();
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_outsider_and_guest_cannot_resolve_magic_action(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(
                route('campaigns.scenes.magic.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]),
                $this->magicPayload(),
            )
            ->assertForbidden();

        auth()->logout();

        $guestResponse = $this->post(
            route('campaigns.scenes.magic.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]),
            $this->magicPayload(),
        );

        $guestResponse->assertRedirect();
        $this->assertStringContainsString('/login', (string) $guestResponse->headers->get('Location'));
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_insufficient_ae_returns_error_without_mutation_or_post(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $casterUser = User::factory()->create();
        $targetUser = User::factory()->create();
        $this->grantMembership($campaign, $casterUser, CampaignMembershipRole::PLAYER, $owner);
        $this->grantMembership($campaign, $targetUser, CampaignMembershipRole::PLAYER, $owner);

        $casterCharacter = $this->characterInCampaignWorld($casterUser, (int) $campaign->world_id, [
            'name' => 'Eldrin',
            'ae_max' => 5,
            'ae_current' => 1,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($targetUser, (int) $campaign->world_id, [
            'name' => 'Hafenräuber I',
            'le_max' => 20,
            'le_current' => 20,
        ]);

        $response = $this->actingAs($owner)
            ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->post(route('campaigns.scenes.magic.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]), $this->magicPayload([
                'actor_type' => 'character',
                'actor_character_id' => $casterCharacter->id,
                'target_type' => 'character',
                'target_character_id' => $targetCharacter->id,
                'ae_cost' => 4,
                'effect_type' => 'le_damage',
                'effect_amount' => 7,
            ]));

        $response->assertRedirect(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]).'#magic-action-tool');
        $response->assertSessionHasErrors(['ae_cost']);

        $casterCharacter->refresh();
        $targetCharacter->refresh();
        $this->assertSame(1, (int) $casterCharacter->ae_current);
        $this->assertSame(20, (int) $targetCharacter->le_current);
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_spell_miss_consumes_ae_but_applies_no_effect(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $casterUser = User::factory()->create();
        $targetUser = User::factory()->create();
        $this->grantMembership($campaign, $casterUser, CampaignMembershipRole::PLAYER, $owner);
        $this->grantMembership($campaign, $targetUser, CampaignMembershipRole::PLAYER, $owner);

        $casterCharacter = $this->characterInCampaignWorld($casterUser, (int) $campaign->world_id, [
            'ae_max' => 9,
            'ae_current' => 9,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($targetUser, (int) $campaign->world_id, [
            'le_max' => 18,
            'le_current' => 18,
        ]);

        $this->bindProbeRoller([95]);

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.magic.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]), $this->magicPayload([
                'actor_type' => 'character',
                'actor_character_id' => $casterCharacter->id,
                'target_type' => 'character',
                'target_character_id' => $targetCharacter->id,
                'spell_target_value' => 60,
                'ae_cost' => 2,
                'defense_target_value' => 55,
                'effect_type' => 'le_damage',
                'effect_amount' => 8,
            ]))
            ->assertRedirect();

        $casterCharacter->refresh();
        $targetCharacter->refresh();
        $this->assertSame(7, (int) $casterCharacter->ae_current);
        $this->assertSame(18, (int) $targetCharacter->le_current);

        $magicPost = Post::query()->latest('id')->firstOrFail();
        $this->assertStringContainsString('Zauberwurf: 95 / 60 -> misslungen', (string) $magicPost->content);
        $this->assertStringContainsString('Ergebnis: Der Zauber misslingt. Keine Wirkung.', (string) $magicPost->content);
    }

    public function test_successful_defense_prevents_effect_but_ae_is_paid(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $casterUser = User::factory()->create();
        $targetUser = User::factory()->create();
        $this->grantMembership($campaign, $casterUser, CampaignMembershipRole::PLAYER, $owner);
        $this->grantMembership($campaign, $targetUser, CampaignMembershipRole::PLAYER, $owner);

        $casterCharacter = $this->characterInCampaignWorld($casterUser, (int) $campaign->world_id, [
            'ae_max' => 9,
            'ae_current' => 9,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($targetUser, (int) $campaign->world_id, [
            'le_max' => 25,
            'le_current' => 25,
        ]);

        $this->bindProbeRoller([31, 22]);

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.magic.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]), $this->magicPayload([
                'actor_type' => 'character',
                'actor_character_id' => $casterCharacter->id,
                'target_type' => 'character',
                'target_character_id' => $targetCharacter->id,
                'spell_target_value' => 60,
                'ae_cost' => 3,
                'defense_label' => 'Magieabwehr',
                'defense_target_value' => 45,
                'effect_type' => 'le_damage',
                'effect_amount' => 8,
            ]))
            ->assertRedirect();

        $casterCharacter->refresh();
        $targetCharacter->refresh();
        $this->assertSame(6, (int) $casterCharacter->ae_current);
        $this->assertSame(25, (int) $targetCharacter->le_current);

        $magicPost = Post::query()->latest('id')->firstOrFail();
        $this->assertStringContainsString('Magieabwehr: 22 / 45 -> Erfolg', (string) $magicPost->content);
        $this->assertStringContainsString('Ergebnis: Die Wirkung wird abgewehrt. Kein Effekt.', (string) $magicPost->content);
    }

    public function test_attribute_delta_effect_updates_current_attribute_and_renders_result(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $casterUser = User::factory()->create();
        $targetUser = User::factory()->create();
        $this->grantMembership($campaign, $casterUser, CampaignMembershipRole::PLAYER, $owner);
        $this->grantMembership($campaign, $targetUser, CampaignMembershipRole::PLAYER, $owner);

        $casterCharacter = $this->characterInCampaignWorld($casterUser, (int) $campaign->world_id, [
            'ae_max' => 8,
            'ae_current' => 8,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($targetUser, (int) $campaign->world_id, [
            'ch' => 60,
            'ch_current' => 60,
        ]);

        $this->bindProbeRoller([20]);

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.magic.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]), $this->magicPayload([
                'actor_type' => 'character',
                'actor_character_id' => $casterCharacter->id,
                'target_type' => 'character',
                'target_character_id' => $targetCharacter->id,
                'spell_name' => 'Schamwelle',
                'ae_cost' => 1,
                'effect_type' => 'attribute_delta',
                'effect_amount' => -15,
                'target_attribute_key' => 'ch',
            ]))
            ->assertRedirect();

        $targetCharacter->refresh();
        $this->assertSame(45, (int) $targetCharacter->ch_current);

        $magicPost = Post::query()->latest('id')->firstOrFail();
        $this->assertStringContainsString('Wirkung: Charisma -15 %', (string) $magicPost->content);
        $this->assertStringContainsString('Ergebnis: Ziel: Charisma 60 % / 45 %', (string) $magicPost->content);
    }

    public function test_narrative_effect_creates_post_without_persistent_target_changes(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $casterUser = User::factory()->create();
        $targetUser = User::factory()->create();
        $this->grantMembership($campaign, $casterUser, CampaignMembershipRole::PLAYER, $owner);
        $this->grantMembership($campaign, $targetUser, CampaignMembershipRole::PLAYER, $owner);

        $casterCharacter = $this->characterInCampaignWorld($casterUser, (int) $campaign->world_id, [
            'ae_max' => 7,
            'ae_current' => 7,
        ]);
        $targetCharacter = $this->characterInCampaignWorld($targetUser, (int) $campaign->world_id, [
            'le_max' => 21,
            'le_current' => 17,
            'ae_max' => 5,
            'ae_current' => 3,
            'mu' => 40,
            'mu_current' => 32,
        ]);

        $this->bindProbeRoller([18]);

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.magic.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]), $this->magicPayload([
                'actor_type' => 'character',
                'actor_character_id' => $casterCharacter->id,
                'target_type' => 'character',
                'target_character_id' => $targetCharacter->id,
                'spell_name' => 'Donnerwort',
                'ae_cost' => 2,
                'effect_type' => 'narrative',
                'effect_amount' => 0,
            ]))
            ->assertRedirect();

        $casterCharacter->refresh();
        $targetCharacter->refresh();
        $this->assertSame(5, (int) $casterCharacter->ae_current);
        $this->assertSame(17, (int) $targetCharacter->le_current);
        $this->assertSame(3, (int) $targetCharacter->ae_current);
        $this->assertSame(32, (int) $targetCharacter->mu_current);

        $magicPost = Post::query()->latest('id')->firstOrFail();
        $this->assertStringContainsString('Wirkung: erzählerischer Effekt', (string) $magicPost->content);
        $this->assertStringContainsString('nicht numerisch erfassten Effekt', (string) $magicPost->content);
    }

    public function test_npc_target_is_supported_without_character_mutation(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $casterUser = User::factory()->create();
        $this->grantMembership($campaign, $casterUser, CampaignMembershipRole::PLAYER, $owner);

        $casterCharacter = $this->characterInCampaignWorld($casterUser, (int) $campaign->world_id, [
            'name' => 'Mara',
            'ae_max' => 8,
            'ae_current' => 8,
        ]);
        $untouchedCharacter = $this->characterInCampaignWorld($casterUser, (int) $campaign->world_id, [
            'name' => 'Unbeteiligt',
            'le_max' => 20,
            'le_current' => 20,
        ]);

        $this->bindProbeRoller([24]);

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.magic.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]), $this->magicPayload([
                'actor_type' => 'character',
                'actor_character_id' => $casterCharacter->id,
                'target_type' => 'npc',
                'target_name' => 'Nebelhund',
                'target_le_current' => 18,
                'target_le_max' => 20,
                'spell_name' => 'Funkenlanze',
                'ae_cost' => 2,
                'effect_type' => 'le_damage',
                'effect_amount' => 8,
            ]))
            ->assertRedirect();

        $untouchedCharacter->refresh();
        $this->assertSame(20, (int) $untouchedCharacter->le_current);

        $magicPost = Post::query()->latest('id')->firstOrFail();
        $this->assertStringContainsString('Ziel: Nebelhund', (string) $magicPost->content);
        $this->assertStringContainsString('Wirkung: 8 LE Schaden', (string) $magicPost->content);
        $this->assertStringContainsString('LE: 10 / 20', (string) $magicPost->content);
    }

    public function test_world_campaign_scene_guard_blocks_foreign_world_context(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();
        $foreignWorld = World::factory()->create([
            'slug' => 'magic-fremdwelt',
            'is_active' => true,
            'position' => -410,
        ]);

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.magic.actions.store', ['world' => $foreignWorld, 'campaign' => $campaign, 'scene' => $scene]), $this->magicPayload())
            ->assertNotFound();
    }

    public function test_validation_errors_redirect_back_without_mutations_or_post(): void
    {
        config(['features.combat_tools_enabled' => true]);

        [$owner, $campaign, $scene] = $this->campaignContext();

        $response = $this->actingAs($owner)
            ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->post(route('campaigns.scenes.magic.actions.store', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]), [
                'actor_type' => 'npc',
                'actor_name' => '',
                'target_type' => 'npc',
                'target_name' => 'Wache',
                'spell_name' => '',
                'spell_target_value' => null,
                'ae_cost' => 2,
                'effect_type' => 'le_damage',
            ]);

        $response->assertRedirect(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]));
        $response->assertSessionHasErrors(['actor_name', 'spell_name', 'spell_target_value']);
        $this->assertDatabaseCount('posts', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function magicPayload(array $overrides = []): array
    {
        return array_merge([
            'actor_type' => 'npc',
            'actor_name' => 'Hexer',
            'actor_ae_current' => null,
            'actor_ae_max' => null,
            'target_type' => 'npc',
            'target_name' => 'Wache',
            'target_le_current' => null,
            'target_le_max' => null,
            'target_ae_current' => null,
            'target_ae_max' => null,
            'spell_name' => 'Zauberprobe',
            'spell_target_value' => 60,
            'spell_roll_mode' => 'normal',
            'spell_modifier' => 0,
            'ae_cost' => 0,
            'defense_label' => null,
            'defense_target_value' => null,
            'defense_roll_mode' => 'normal',
            'defense_modifier' => 0,
            'effect_type' => 'narrative',
            'effect_amount' => 0,
            'target_attribute_key' => null,
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
