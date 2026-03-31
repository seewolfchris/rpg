<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationWorldContextMutationMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_scene_store_role_matrix_for_active_world(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);

        $cases = [
            'owner' => [$owner, 302],
            'co-gm' => [$coGm, 302],
            'admin' => [$admin, 302],
            'player' => [$player, 403],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $slug = 'a3-scene-'.$suffix;

            $response = $this->actingAs($actor)->post(route('campaigns.scenes.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]), $this->scenePayload($slug, 'A3 '.$suffix));

            if ($expectedStatus === 302) {
                $response->assertRedirect();
                $this->assertDatabaseHas('scenes', [
                    'campaign_id' => $campaign->id,
                    'slug' => $slug,
                    'created_by' => $actor->id,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseMissing('scenes', [
                'campaign_id' => $campaign->id,
                'slug' => $slug,
            ]);
        }
    }

    public function test_scene_store_in_inactive_world_returns_404_for_owner_co_gm_and_admin(): void
    {
        [$campaign, $owner, $coGm, , , $admin] = $this->seedCampaignRoleMatrix(worldActive: false);

        $actors = [
            'owner' => $owner,
            'co-gm' => $coGm,
            'admin' => $admin,
        ];

        foreach ($actors as $suffix => $actor) {
            $slug = 'a3-inactive-'.$suffix;

            $this->actingAs($actor)->post(route('campaigns.scenes.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]), $this->scenePayload($slug, 'A3 inactive '.$suffix))
                ->assertNotFound();

            $this->assertDatabaseMissing('scenes', [
                'campaign_id' => $campaign->id,
                'slug' => $slug,
            ]);
        }
    }

    public function test_post_moderation_role_matrix_and_world_context_guards(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $cases = [
            'owner' => [$owner, 302],
            'co-gm' => [$coGm, 302],
            'admin' => [$admin, 302],
            'player' => [$player, 403],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $post = Post::factory()->create([
                'scene_id' => $scene->id,
                'user_id' => $player->id,
                'moderation_status' => 'pending',
                'approved_at' => null,
                'approved_by' => null,
            ]);

            $response = $this->actingAs($actor)->patch(route('posts.moderate', [
                'world' => $campaign->world,
                'post' => $post,
            ]), [
                'moderation_status' => 'approved',
                'moderation_note' => 'A3 matrix '.$suffix,
            ]);

            if ($expectedStatus === 302) {
                $response->assertRedirect();
                $this->assertDatabaseHas('posts', [
                    'id' => $post->id,
                    'moderation_status' => 'approved',
                    'approved_by' => $actor->id,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseHas('posts', [
                'id' => $post->id,
                'moderation_status' => 'pending',
                'approved_by' => null,
            ]);
        }

        $foreignActiveWorld = World::factory()->create([
            'slug' => 'a3-fremd-aktive-welt',
            'is_active' => true,
            'position' => -200,
        ]);
        $inactiveWorld = World::factory()->create([
            'slug' => 'a3-inaktive-welt',
            'is_active' => false,
            'position' => -210,
        ]);

        $worldGuardPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $this->actingAs($owner)->patch(route('posts.moderate', [
            'world' => $foreignActiveWorld,
            'post' => $worldGuardPost,
        ]), [
            'moderation_status' => 'approved',
            'moderation_note' => 'A3 wrong world',
        ])->assertNotFound();

        $this->assertDatabaseHas('posts', [
            'id' => $worldGuardPost->id,
            'moderation_status' => 'pending',
            'approved_by' => null,
        ]);

        $this->actingAs($owner)->patch(route('posts.moderate', [
            'world' => $inactiveWorld,
            'post' => $worldGuardPost,
        ]), [
            'moderation_status' => 'approved',
            'moderation_note' => 'A3 inactive world',
        ])->assertNotFound();

        $this->assertDatabaseHas('posts', [
            'id' => $worldGuardPost->id,
            'moderation_status' => 'pending',
            'approved_by' => null,
        ]);
    }

    public function test_character_inline_update_ownership_matrix(): void
    {
        $owner = User::factory()->create();
        $gm = User::factory()->gm()->create();
        $admin = User::factory()->admin()->create();
        $outsider = User::factory()->create();

        $cases = [
            'owner' => [$owner, 302],
            'gm' => [$gm, 302],
            'admin' => [$admin, 302],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $character = Character::factory()->create([
                'user_id' => $owner->id,
                'status' => 'active',
                'bio' => 'Ausgangszustand',
                'concept' => 'Baseline',
            ]);

            $response = $this->actingAs($actor)->patch(route('characters.inline-update', ['character' => $character]), [
                'status' => 'active',
                'bio' => 'A3 Matrix Bio '.$suffix,
                'concept' => 'A3 Matrix '.$suffix,
            ]);

            if ($expectedStatus === 302) {
                $response->assertRedirect(route('characters.show', ['character' => $character]));
                $this->assertDatabaseHas('characters', [
                    'id' => $character->id,
                    'concept' => 'A3 Matrix '.$suffix,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseHas('characters', [
                'id' => $character->id,
                'concept' => 'Baseline',
            ]);
        }
    }

    public function test_gm_progression_award_xp_role_matrix_for_active_world(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $cases = [
            'owner' => [$owner, 302],
            'co-gm' => [$coGm, 302],
            'admin' => [$admin, 302],
            'player' => [$player, 403],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $character = Character::factory()->create([
                'user_id' => $player->id,
                'world_id' => $campaign->world_id,
                'xp_total' => 0,
                'level' => 1,
            ]);

            $response = $this->actingAs($actor)->post(route('gm.progression.award-xp', [
                'world' => $campaign->world,
            ]), $this->gmProgressionPayload(
                campaignId: (int) $campaign->id,
                sceneId: (int) $scene->id,
                characterId: (int) $character->id,
                reason: 'A3 progression role '.$suffix,
            ));

            if ($expectedStatus === 302) {
                $response->assertRedirect(route('gm.progression.index', [
                    'world' => $campaign->world,
                    'campaign_id' => $campaign->id,
                ]));

                $this->assertDatabaseHas('characters', [
                    'id' => $character->id,
                    'xp_total' => 30,
                ]);
                $this->assertDatabaseHas('character_progression_events', [
                    'character_id' => $character->id,
                    'actor_user_id' => $actor->id,
                    'campaign_id' => $campaign->id,
                    'scene_id' => $scene->id,
                    'xp_delta' => 30,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseHas('characters', [
                'id' => $character->id,
                'xp_total' => 0,
            ]);
            $this->assertDatabaseMissing('character_progression_events', [
                'character_id' => $character->id,
                'actor_user_id' => $actor->id,
                'campaign_id' => $campaign->id,
            ]);
        }
    }

    public function test_gm_progression_award_xp_ownership_and_world_context_guards(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);
        unset($coGm, $admin);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $participantCharacter = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $campaign->world_id,
            'xp_total' => 0,
            'level' => 1,
        ]);
        $foreignCharacter = Character::factory()->create([
            'user_id' => $outsider->id,
            'world_id' => $campaign->world_id,
            'xp_total' => 0,
            'level' => 1,
        ]);

        $this->actingAs($owner)->post(route('gm.progression.award-xp', [
            'world' => $campaign->world,
        ]), $this->gmProgressionPayload(
            campaignId: (int) $campaign->id,
            sceneId: (int) $scene->id,
            characterId: (int) $participantCharacter->id,
            reason: 'A3 progression ownership ok',
        ))->assertRedirect(route('gm.progression.index', [
            'world' => $campaign->world,
            'campaign_id' => $campaign->id,
        ]));

        $this->assertDatabaseHas('characters', [
            'id' => $participantCharacter->id,
            'xp_total' => 30,
        ]);

        $this->actingAs($owner)
            ->from(route('gm.progression.index', ['world' => $campaign->world, 'campaign_id' => $campaign->id]))
            ->post(route('gm.progression.award-xp', [
                'world' => $campaign->world,
            ]), $this->gmProgressionPayload(
                campaignId: (int) $campaign->id,
                sceneId: (int) $scene->id,
                characterId: (int) $foreignCharacter->id,
                reason: 'A3 progression ownership reject',
            ))
            ->assertRedirect(route('gm.progression.index', ['world' => $campaign->world, 'campaign_id' => $campaign->id]))
            ->assertSessionHasErrors('awards.0.character_id');

        $this->assertDatabaseHas('characters', [
            'id' => $foreignCharacter->id,
            'xp_total' => 0,
        ]);
        $this->assertDatabaseHas('characters', [
            'id' => $participantCharacter->id,
            'xp_total' => 30,
        ]);

        $foreignActiveWorld = World::factory()->create([
            'slug' => 'a3-progression-fremd-aktiv',
            'is_active' => true,
            'position' => -330,
        ]);
        $inactiveWorld = World::factory()->create([
            'slug' => 'a3-progression-inaktiv',
            'is_active' => false,
            'position' => -340,
        ]);

        $this->actingAs($owner)
            ->from(route('gm.progression.index', ['world' => $foreignActiveWorld]))
            ->post(route('gm.progression.award-xp', [
                'world' => $foreignActiveWorld,
            ]), $this->gmProgressionPayload(
                campaignId: (int) $campaign->id,
                sceneId: (int) $scene->id,
                characterId: (int) $participantCharacter->id,
                reason: 'A3 progression wrong world',
            ))
            ->assertRedirect(route('gm.progression.index', ['world' => $foreignActiveWorld]))
            ->assertSessionHasErrors('campaign_id');

        $this->assertDatabaseHas('characters', [
            'id' => $participantCharacter->id,
            'xp_total' => 30,
        ]);

        $this->actingAs($owner)->post(route('gm.progression.award-xp', [
            'world' => $inactiveWorld,
        ]), $this->gmProgressionPayload(
            campaignId: (int) $campaign->id,
            sceneId: (int) $scene->id,
            characterId: (int) $participantCharacter->id,
            reason: 'A3 progression inactive world',
        ))->assertNotFound();

        $this->assertDatabaseHas('characters', [
            'id' => $participantCharacter->id,
            'xp_total' => 30,
        ]);
    }

    public function test_scene_inventory_quick_action_role_matrix_for_active_world(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $cases = [
            'owner' => [$owner, true],
            'co-gm' => [$coGm, true],
            'admin' => [$admin, true],
            'player' => [$player, false],
            'outsider' => [$outsider, false],
        ];

        foreach ($cases as $suffix => [$actor, $shouldMutate]) {
            $character = Character::factory()->create([
                'user_id' => $player->id,
                'world_id' => $campaign->world_id,
                'inventory' => ['Fackel'],
            ]);

            $response = $this->actingAs($actor)
                ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
                ->post(route('campaigns.scenes.inventory-quick-action', [
                    'world' => $campaign->world,
                    'campaign' => $campaign,
                    'scene' => $scene,
                ]), $this->inventoryQuickActionPayload((int) $character->id, [
                    'inventory_action_item' => 'Heiltrank '.$suffix,
                ]));

            $expectedRedirect = route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]);

            $response->assertRedirect(
                $shouldMutate ? $expectedRedirect.'#inventory-quick-action' : $expectedRedirect
            );

            $character->refresh();
            $inventory = is_array($character->inventory) ? $character->inventory : [];

            if ($shouldMutate) {
                $this->assertCount(2, $inventory);
                $this->assertSame('Heiltrank '.$suffix, (string) ($inventory[1]['name'] ?? ''));
                $this->assertSame(2, (int) ($inventory[1]['quantity'] ?? 0));
                $this->assertDatabaseHas('character_inventory_logs', [
                    'character_id' => $character->id,
                    'actor_user_id' => $actor->id,
                    'source' => 'scene_inventory_quick_action',
                    'action' => 'add',
                    'item_name' => 'Heiltrank '.$suffix,
                    'quantity' => 2,
                ]);

                continue;
            }

            $response->assertSessionHasErrors('inventory_action_character_id');
            $this->assertSame(['Fackel'], $inventory);
            $this->assertDatabaseMissing('character_inventory_logs', [
                'character_id' => $character->id,
                'actor_user_id' => $actor->id,
                'source' => 'scene_inventory_quick_action',
                'action' => 'add',
                'item_name' => 'Heiltrank '.$suffix,
            ]);
        }
    }

    public function test_scene_inventory_quick_action_ownership_and_world_context_guards(): void
    {
        [$campaign, $owner, , $player, $outsider] = $this->seedCampaignRoleMatrix(worldActive: true);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $participantCharacter = Character::factory()->create([
            'user_id' => $player->id,
            'world_id' => $campaign->world_id,
            'inventory' => ['Fackel'],
        ]);
        $nonParticipantCharacter = Character::factory()->create([
            'user_id' => $outsider->id,
            'world_id' => $campaign->world_id,
            'inventory' => ['Dolch'],
        ]);

        $this->actingAs($owner)
            ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->post(route('campaigns.scenes.inventory-quick-action', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), $this->inventoryQuickActionPayload((int) $participantCharacter->id, [
                'inventory_action_item' => 'Seil',
            ]))
            ->assertRedirect(route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]).'#inventory-quick-action');

        $this->actingAs($owner)
            ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->post(route('campaigns.scenes.inventory-quick-action', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), $this->inventoryQuickActionPayload((int) $nonParticipantCharacter->id, [
                'inventory_action_item' => 'Heiltrank',
            ]))
            ->assertRedirect(route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->assertSessionHasErrors('inventory_action_character_id');

        $this->assertSame(['Dolch'], $nonParticipantCharacter->fresh()->inventory);

        $foreignActiveWorld = World::factory()->create([
            'slug' => 'a3-inventory-fremd-aktiv',
            'is_active' => true,
            'position' => -350,
        ]);

        $this->actingAs($owner)->post(route('campaigns.scenes.inventory-quick-action', [
            'world' => $foreignActiveWorld,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), $this->inventoryQuickActionPayload((int) $participantCharacter->id, [
            'inventory_action_item' => 'Fremdwelt',
        ]))->assertNotFound();

        [$inactiveCampaign, $inactiveOwner, , $inactivePlayer] = $this->seedCampaignRoleMatrix(worldActive: false);
        $inactiveScene = Scene::factory()->create([
            'campaign_id' => $inactiveCampaign->id,
            'created_by' => $inactiveOwner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $inactiveParticipantCharacter = Character::factory()->create([
            'user_id' => $inactivePlayer->id,
            'world_id' => $inactiveCampaign->world_id,
            'inventory' => ['Fackel'],
        ]);

        $this->actingAs($inactiveOwner)->post(route('campaigns.scenes.inventory-quick-action', [
            'world' => $inactiveCampaign->world,
            'campaign' => $inactiveCampaign,
            'scene' => $inactiveScene,
        ]), $this->inventoryQuickActionPayload((int) $inactiveParticipantCharacter->id, [
            'inventory_action_item' => 'Inaktive Welt',
        ]))->assertNotFound();

        $this->assertSame(['Fackel'], $inactiveParticipantCharacter->fresh()->inventory);
    }

    public function test_scene_subscriptions_bulk_update_role_ownership_and_world_guards(): void
    {
        $world = World::factory()->create([
            'slug' => 'a3-subs-aktiv',
            'is_active' => true,
            'position' => -360,
        ]);
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();
        $player = User::factory()->create();
        $outsider = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $victim = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $world->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $this->acceptInvitation($campaign, $coGm, CampaignInvitation::ROLE_CO_GM, $owner);
        $this->acceptInvitation($campaign, $player, CampaignInvitation::ROLE_PLAYER, $owner);

        $cases = [
            'owner' => $owner,
            'co-gm' => $coGm,
            'admin' => $admin,
            'player' => $player,
            'outsider' => $outsider,
        ];

        foreach ($cases as $suffix => $actor) {
            $scene = Scene::factory()->create([
                'campaign_id' => $campaign->id,
                'created_by' => $owner->id,
                'status' => 'open',
                'allow_ooc' => true,
                'title' => 'A3 Sub Szene '.$suffix,
            ]);

            $ownSubscription = SceneSubscription::query()->create([
                'scene_id' => $scene->id,
                'user_id' => $actor->id,
                'is_muted' => false,
                'last_read_post_id' => null,
                'last_read_at' => null,
            ]);
            $victimSubscription = SceneSubscription::query()->create([
                'scene_id' => $scene->id,
                'user_id' => $victim->id,
                'is_muted' => false,
                'last_read_post_id' => null,
                'last_read_at' => null,
            ]);

            $this->actingAs($actor)->patch(route('scene-subscriptions.bulk-update', [
                'world' => $world,
            ]), $this->sceneSubscriptionBulkPayload(
                action: 'mute_filtered',
                status: 'active',
                search: 'A3 Sub Szene '.$suffix,
            ))->assertRedirect(route('scene-subscriptions.index', [
                'world' => $world,
                'status' => 'active',
                'q' => 'A3 Sub Szene '.$suffix,
            ]));

            $this->assertDatabaseHas('scene_subscriptions', [
                'id' => $ownSubscription->id,
                'is_muted' => true,
            ]);
            $this->assertDatabaseHas('scene_subscriptions', [
                'id' => $victimSubscription->id,
                'is_muted' => false,
            ]);
        }

        $otherWorld = World::factory()->create([
            'slug' => 'a3-subs-foreign-active',
            'is_active' => true,
            'position' => -361,
        ]);
        $crossWorldCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $otherWorld->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $crossWorldScene = Scene::factory()->create([
            'campaign_id' => $crossWorldCampaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $mainWorldScene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $mainWorldSubscription = SceneSubscription::query()->create([
            'scene_id' => $mainWorldScene->id,
            'user_id' => $player->id,
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);
        $otherWorldSubscription = SceneSubscription::query()->create([
            'scene_id' => $crossWorldScene->id,
            'user_id' => $player->id,
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);

        $this->actingAs($player)->patch(route('scene-subscriptions.bulk-update', [
            'world' => $otherWorld,
        ]), $this->sceneSubscriptionBulkPayload(
            action: 'mute_all_active',
            status: 'all',
            search: '',
        ))->assertRedirect(route('scene-subscriptions.index', [
            'world' => $otherWorld,
            'status' => 'all',
        ]));

        $this->assertDatabaseHas('scene_subscriptions', [
            'id' => $mainWorldSubscription->id,
            'is_muted' => false,
        ]);
        $this->assertDatabaseHas('scene_subscriptions', [
            'id' => $otherWorldSubscription->id,
            'is_muted' => true,
        ]);

        $inactiveWorld = World::factory()->create([
            'slug' => 'a3-subs-inaktiv',
            'is_active' => false,
            'position' => -362,
        ]);
        $inactiveCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $inactiveWorld->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $inactiveScene = Scene::factory()->create([
            'campaign_id' => $inactiveCampaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $inactiveSubscription = SceneSubscription::query()->create([
            'scene_id' => $inactiveScene->id,
            'user_id' => $player->id,
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);

        $this->actingAs($player)->patch(route('scene-subscriptions.bulk-update', [
            'world' => $inactiveWorld,
        ]), $this->sceneSubscriptionBulkPayload(
            action: 'mute_all_active',
            status: 'all',
            search: '',
        ))->assertNotFound();

        $this->assertDatabaseHas('scene_subscriptions', [
            'id' => $inactiveSubscription->id,
            'is_muted' => false,
        ]);
    }

    public function test_gm_moderation_bulk_update_role_scope_and_world_guards(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $cases = [
            'owner' => [$owner, 302],
            'co-gm' => [$coGm, 302],
            'admin' => [$admin, 302],
            'player' => [$player, 403],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $post = Post::factory()->create([
                'scene_id' => $scene->id,
                'user_id' => $player->id,
                'content' => 'A3 Bulk Moderation '.$suffix,
                'moderation_status' => 'pending',
                'approved_at' => null,
                'approved_by' => null,
            ]);

            $response = $this->actingAs($actor)->patch(route('gm.moderation.bulk-update', [
                'world' => $campaign->world,
            ]), $this->gmBulkModerationPayload(
                moderationStatus: 'approved',
                postIds: [(int) $post->id],
            ));

            if ($expectedStatus === 302) {
                $response->assertRedirect(route('gm.moderation.index', [
                    'world' => $campaign->world,
                    'status' => 'all',
                ]));

                $this->assertDatabaseHas('posts', [
                    'id' => $post->id,
                    'moderation_status' => 'approved',
                    'approved_by' => $actor->id,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseHas('posts', [
                'id' => $post->id,
                'moderation_status' => 'pending',
                'approved_by' => null,
            ]);
        }

        $foreignOwner = User::factory()->gm()->create();
        $foreignCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'world_id' => $campaign->world_id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $foreignScene = Scene::factory()->create([
            'campaign_id' => $foreignCampaign->id,
            'created_by' => $foreignOwner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $foreignPost = Post::factory()->create([
            'scene_id' => $foreignScene->id,
            'user_id' => $player->id,
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $this->actingAs($coGm)->patch(route('gm.moderation.bulk-update', [
            'world' => $campaign->world,
        ]), $this->gmBulkModerationPayload(
            moderationStatus: 'approved',
            postIds: [(int) $foreignPost->id],
        ))->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $foreignPost->id,
            'moderation_status' => 'pending',
            'approved_by' => null,
        ]);

        $foreignWorld = World::factory()->create([
            'slug' => 'a3-bulk-moderation-fremd-aktiv',
            'is_active' => true,
            'position' => -370,
        ]);
        $worldGuardPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $this->actingAs($owner)->patch(route('gm.moderation.bulk-update', [
            'world' => $foreignWorld,
        ]), $this->gmBulkModerationPayload(
            moderationStatus: 'approved',
            postIds: [(int) $worldGuardPost->id],
        ))->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $worldGuardPost->id,
            'moderation_status' => 'pending',
            'approved_by' => null,
        ]);

        $inactiveWorld = World::factory()->create([
            'slug' => 'a3-bulk-moderation-inaktiv',
            'is_active' => false,
            'position' => -371,
        ]);

        $this->actingAs($owner)->patch(route('gm.moderation.bulk-update', [
            'world' => $inactiveWorld,
        ]), $this->gmBulkModerationPayload(
            moderationStatus: 'approved',
            postIds: [(int) $worldGuardPost->id],
        ))->assertNotFound();

        $this->assertDatabaseHas('posts', [
            'id' => $worldGuardPost->id,
            'moderation_status' => 'pending',
            'approved_by' => null,
        ]);
    }

    public function test_campaign_invitation_store_role_and_world_guard_matrix(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);
        $foreignGm = User::factory()->gm()->create();

        $cases = [
            'owner' => [$owner, 302],
            'foreign-gm' => [$foreignGm, 302],
            'admin' => [$admin, 302],
            'co-gm' => [$coGm, 403],
            'player' => [$player, 403],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $invitee = User::factory()->create([
                'email' => 'a3-invitee-'.$suffix.'@example.test',
            ]);

            $response = $this->actingAs($actor)->post(route('campaigns.invitations.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]), $this->campaignInvitationPayload((string) $invitee->email, CampaignInvitation::ROLE_PLAYER));

            if ($expectedStatus === 302) {
                $response->assertRedirect(route('campaigns.show', [
                    'world' => $campaign->world,
                    'campaign' => $campaign,
                ]));
                $this->assertDatabaseHas('campaign_invitations', [
                    'campaign_id' => $campaign->id,
                    'user_id' => $invitee->id,
                    'invited_by' => $actor->id,
                    'status' => CampaignInvitation::STATUS_PENDING,
                    'role' => CampaignInvitation::ROLE_PLAYER,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseMissing('campaign_invitations', [
                'campaign_id' => $campaign->id,
                'user_id' => $invitee->id,
            ]);
        }

        $foreignWorld = World::factory()->create([
            'slug' => 'a3-invite-store-fremd-aktiv',
            'is_active' => true,
            'position' => -380,
        ]);
        $worldGuardInvitee = User::factory()->create([
            'email' => 'a3-world-guard-store@example.test',
        ]);

        $this->actingAs($owner)->post(route('campaigns.invitations.store', [
            'world' => $foreignWorld,
            'campaign' => $campaign,
        ]), $this->campaignInvitationPayload(
            email: (string) $worldGuardInvitee->email,
            role: CampaignInvitation::ROLE_PLAYER,
        ))->assertNotFound();

        $this->assertDatabaseMissing('campaign_invitations', [
            'campaign_id' => $campaign->id,
            'user_id' => $worldGuardInvitee->id,
        ]);

        [$inactiveCampaign, $inactiveOwner] = $this->seedCampaignRoleMatrix(worldActive: false);
        $inactiveInvitee = User::factory()->create([
            'email' => 'a3-inactive-store@example.test',
        ]);

        $this->actingAs($inactiveOwner)->post(route('campaigns.invitations.store', [
            'world' => $inactiveCampaign->world,
            'campaign' => $inactiveCampaign,
        ]), $this->campaignInvitationPayload(
            email: (string) $inactiveInvitee->email,
            role: CampaignInvitation::ROLE_PLAYER,
        ))->assertNotFound();

        $this->assertDatabaseMissing('campaign_invitations', [
            'campaign_id' => $inactiveCampaign->id,
            'user_id' => $inactiveInvitee->id,
        ]);
    }

    public function test_campaign_invitation_destroy_role_and_world_guard_matrix(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);
        $foreignGm = User::factory()->gm()->create();

        $cases = [
            'owner' => [$owner, 302],
            'foreign-gm' => [$foreignGm, 302],
            'admin' => [$admin, 302],
            'co-gm' => [$coGm, 403],
            'player' => [$player, 403],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $invitee = User::factory()->create([
                'email' => 'a3-destroy-invitee-'.$suffix.'@example.test',
            ]);
            $invitation = CampaignInvitation::query()->create([
                'campaign_id' => $campaign->id,
                'user_id' => $invitee->id,
                'invited_by' => $owner->id,
                'status' => CampaignInvitation::STATUS_PENDING,
                'role' => CampaignInvitation::ROLE_PLAYER,
                'accepted_at' => null,
                'responded_at' => null,
            ]);

            $response = $this->actingAs($actor)->delete(route('campaigns.invitations.destroy', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'invitation' => $invitation,
            ]));

            if ($expectedStatus === 302) {
                $response->assertRedirect(route('campaigns.show', [
                    'world' => $campaign->world,
                    'campaign' => $campaign,
                ]));
                $this->assertDatabaseMissing('campaign_invitations', [
                    'id' => $invitation->id,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseHas('campaign_invitations', [
                'id' => $invitation->id,
            ]);
        }

        $guardInvitee = User::factory()->create([
            'email' => 'a3-destroy-world-guard@example.test',
        ]);
        $guardInvitation = CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $guardInvitee->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => null,
            'responded_at' => null,
        ]);
        $foreignWorld = World::factory()->create([
            'slug' => 'a3-invite-destroy-fremd-aktiv',
            'is_active' => true,
            'position' => -381,
        ]);

        $this->actingAs($owner)->delete(route('campaigns.invitations.destroy', [
            'world' => $foreignWorld,
            'campaign' => $campaign,
            'invitation' => $guardInvitation,
        ]))->assertNotFound();

        $this->assertDatabaseHas('campaign_invitations', [
            'id' => $guardInvitation->id,
        ]);

        [$inactiveCampaign, $inactiveOwner] = $this->seedCampaignRoleMatrix(worldActive: false);
        $inactiveInvitee = User::factory()->create([
            'email' => 'a3-inactive-destroy@example.test',
        ]);
        $inactiveInvitation = CampaignInvitation::query()->create([
            'campaign_id' => $inactiveCampaign->id,
            'user_id' => $inactiveInvitee->id,
            'invited_by' => $inactiveOwner->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => null,
            'responded_at' => null,
        ]);

        $this->actingAs($inactiveOwner)->delete(route('campaigns.invitations.destroy', [
            'world' => $inactiveCampaign->world,
            'campaign' => $inactiveCampaign,
            'invitation' => $inactiveInvitation,
        ]))->assertNotFound();

        $this->assertDatabaseHas('campaign_invitations', [
            'id' => $inactiveInvitation->id,
        ]);
    }

    public function test_posts_update_role_matrix_for_active_world(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $cases = [
            'owner' => [$owner, 302],
            'co-gm' => [$coGm, 302],
            'admin' => [$admin, 302],
            'author-player' => [$player, 302],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $post = Post::factory()->create([
                'scene_id' => $scene->id,
                'user_id' => $player->id,
                'post_type' => 'ooc',
                'content_format' => 'plain',
                'content' => 'A3 Post update baseline '.$suffix,
                'moderation_status' => 'pending',
                'approved_at' => null,
                'approved_by' => null,
            ]);

            $updatedContent = 'A3 Post update changed '.$suffix;

            $response = $this->actingAs($actor)->patch(route('posts.update', [
                'world' => $campaign->world,
                'post' => $post,
            ]), $this->postUpdatePayload($updatedContent));

            if ($expectedStatus === 302) {
                $response->assertRedirect(route('campaigns.scenes.show', [
                    'world' => $campaign->world,
                    'campaign' => $campaign,
                    'scene' => $scene,
                ]).'#post-'.$post->id);
                $this->assertDatabaseHas('posts', [
                    'id' => $post->id,
                    'content' => $updatedContent,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseHas('posts', [
                'id' => $post->id,
                'content' => 'A3 Post update baseline '.$suffix,
            ]);
        }
    }

    public function test_posts_update_co_gm_negative_scope_cases_same_world_and_foreign_world(): void
    {
        [$campaign, $owner, $coGm, $player] = $this->seedCampaignRoleMatrix(worldActive: true);
        $foreignOwner = User::factory()->gm()->create();

        $sameWorldForeignCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'world_id' => $campaign->world_id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $sameWorldForeignScene = Scene::factory()->create([
            'campaign_id' => $sameWorldForeignCampaign->id,
            'created_by' => $foreignOwner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $sameWorldForeignPost = Post::factory()->create([
            'scene_id' => $sameWorldForeignScene->id,
            'user_id' => $player->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'A3 CoGM same world baseline',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $this->actingAs($coGm)->patch(route('posts.update', [
            'world' => $campaign->world,
            'post' => $sameWorldForeignPost,
        ]), $this->postUpdatePayload('A3 blocked same world'))
            ->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $sameWorldForeignPost->id,
            'content' => 'A3 CoGM same world baseline',
        ]);

        $foreignWorld = World::factory()->create([
            'slug' => 'a3-post-update-foreign-world',
            'is_active' => true,
            'position' => -390,
        ]);
        $foreignWorldCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'world_id' => $foreignWorld->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $foreignWorldScene = Scene::factory()->create([
            'campaign_id' => $foreignWorldCampaign->id,
            'created_by' => $foreignOwner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $foreignWorldPost = Post::factory()->create([
            'scene_id' => $foreignWorldScene->id,
            'user_id' => $player->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'A3 CoGM foreign world baseline',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $this->actingAs($coGm)->patch(route('posts.update', [
            'world' => $foreignWorld,
            'post' => $foreignWorldPost,
        ]), $this->postUpdatePayload('A3 blocked foreign world'))
            ->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $foreignWorldPost->id,
            'content' => 'A3 CoGM foreign world baseline',
        ]);

        unset($owner);
    }

    public function test_posts_destroy_role_matrix_for_active_world(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $cases = [
            'owner' => [$owner, 302],
            'co-gm' => [$coGm, 302],
            'admin' => [$admin, 302],
            'author-player' => [$player, 302],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $post = Post::factory()->create([
                'scene_id' => $scene->id,
                'user_id' => $player->id,
                'post_type' => 'ooc',
                'content_format' => 'plain',
                'content' => 'A3 Post destroy baseline '.$suffix,
                'moderation_status' => 'pending',
                'approved_at' => null,
                'approved_by' => null,
            ]);

            $response = $this->actingAs($actor)->delete(route('posts.destroy', [
                'world' => $campaign->world,
                'post' => $post,
            ]));

            if ($expectedStatus === 302) {
                $response->assertRedirect(route('campaigns.scenes.show', [
                    'world' => $campaign->world,
                    'campaign' => $campaign,
                    'scene' => $scene,
                ]));
                $this->assertDatabaseMissing('posts', [
                    'id' => $post->id,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseHas('posts', [
                'id' => $post->id,
                'content' => 'A3 Post destroy baseline '.$suffix,
            ]);
        }
    }

    public function test_posts_destroy_co_gm_negative_scope_cases_same_world_and_foreign_world(): void
    {
        [$campaign, , $coGm, $player] = $this->seedCampaignRoleMatrix(worldActive: true);
        $foreignOwner = User::factory()->gm()->create();

        $sameWorldForeignCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'world_id' => $campaign->world_id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $sameWorldForeignScene = Scene::factory()->create([
            'campaign_id' => $sameWorldForeignCampaign->id,
            'created_by' => $foreignOwner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $sameWorldForeignPost = Post::factory()->create([
            'scene_id' => $sameWorldForeignScene->id,
            'user_id' => $player->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'A3 CoGM destroy same world',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $this->actingAs($coGm)->delete(route('posts.destroy', [
            'world' => $campaign->world,
            'post' => $sameWorldForeignPost,
        ]))->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $sameWorldForeignPost->id,
            'content' => 'A3 CoGM destroy same world',
        ]);

        $foreignWorld = World::factory()->create([
            'slug' => 'a3-post-destroy-foreign-world',
            'is_active' => true,
            'position' => -391,
        ]);
        $foreignWorldCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'world_id' => $foreignWorld->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $foreignWorldScene = Scene::factory()->create([
            'campaign_id' => $foreignWorldCampaign->id,
            'created_by' => $foreignOwner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $foreignWorldPost = Post::factory()->create([
            'scene_id' => $foreignWorldScene->id,
            'user_id' => $player->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'A3 CoGM destroy foreign world',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $this->actingAs($coGm)->delete(route('posts.destroy', [
            'world' => $foreignWorld,
            'post' => $foreignWorldPost,
        ]))->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $foreignWorldPost->id,
            'content' => 'A3 CoGM destroy foreign world',
        ]);
    }

    public function test_scenes_update_role_matrix_for_active_world(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);

        $cases = [
            'owner' => [$owner, 302],
            'co-gm' => [$coGm, 302],
            'admin' => [$admin, 302],
            'player' => [$player, 403],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $scene = Scene::factory()->create([
                'campaign_id' => $campaign->id,
                'created_by' => $owner->id,
                'title' => 'A3 Scene Update Baseline '.$suffix,
                'slug' => 'a3-scene-update-base-'.$suffix,
                'status' => 'open',
                'allow_ooc' => true,
                'mood' => 'neutral',
            ]);

            $updatedSlug = 'a3-scene-update-'.$suffix;
            $updatedTitle = 'A3 Szene Update '.$suffix;

            $response = $this->actingAs($actor)->patch(route('campaigns.scenes.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), $this->sceneUpdatePayload($updatedSlug, $updatedTitle));

            if ($expectedStatus === 302) {
                $response->assertRedirect(route('campaigns.scenes.show', [
                    'world' => $campaign->world,
                    'campaign' => $campaign,
                    'scene' => $scene,
                ]));
                $this->assertDatabaseHas('scenes', [
                    'id' => $scene->id,
                    'slug' => $updatedSlug,
                    'title' => $updatedTitle,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseHas('scenes', [
                'id' => $scene->id,
                'slug' => 'a3-scene-update-base-'.$suffix,
                'title' => 'A3 Scene Update Baseline '.$suffix,
            ]);
        }
    }

    public function test_scenes_update_co_gm_negative_scope_cases_same_world_and_foreign_world(): void
    {
        [$campaign, , $coGm] = $this->seedCampaignRoleMatrix(worldActive: true);
        $foreignOwner = User::factory()->gm()->create();

        $sameWorldForeignCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'world_id' => $campaign->world_id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $sameWorldForeignScene = Scene::factory()->create([
            'campaign_id' => $sameWorldForeignCampaign->id,
            'created_by' => $foreignOwner->id,
            'title' => 'A3 CoGM Same World Scene',
            'slug' => 'a3-cogm-same-world-scene',
            'status' => 'open',
            'allow_ooc' => true,
            'mood' => 'neutral',
        ]);

        $this->actingAs($coGm)->patch(route('campaigns.scenes.update', [
            'world' => $campaign->world,
            'campaign' => $sameWorldForeignCampaign,
            'scene' => $sameWorldForeignScene,
        ]), $this->sceneUpdatePayload(
            slug: 'a3-cogm-same-world-scene-blocked',
            title: 'A3 CoGM same world blocked',
        ))->assertForbidden();

        $this->assertDatabaseHas('scenes', [
            'id' => $sameWorldForeignScene->id,
            'slug' => 'a3-cogm-same-world-scene',
            'title' => 'A3 CoGM Same World Scene',
        ]);

        $foreignWorld = World::factory()->create([
            'slug' => 'a3-scene-update-foreign-world',
            'is_active' => true,
            'position' => -392,
        ]);
        $foreignWorldCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'world_id' => $foreignWorld->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $foreignWorldScene = Scene::factory()->create([
            'campaign_id' => $foreignWorldCampaign->id,
            'created_by' => $foreignOwner->id,
            'title' => 'A3 CoGM Foreign World Scene',
            'slug' => 'a3-cogm-foreign-world-scene',
            'status' => 'open',
            'allow_ooc' => true,
            'mood' => 'neutral',
        ]);

        $this->actingAs($coGm)->patch(route('campaigns.scenes.update', [
            'world' => $foreignWorld,
            'campaign' => $foreignWorldCampaign,
            'scene' => $foreignWorldScene,
        ]), $this->sceneUpdatePayload(
            slug: 'a3-cogm-foreign-world-scene-blocked',
            title: 'A3 CoGM foreign world blocked',
        ))->assertForbidden();

        $this->assertDatabaseHas('scenes', [
            'id' => $foreignWorldScene->id,
            'slug' => 'a3-cogm-foreign-world-scene',
            'title' => 'A3 CoGM Foreign World Scene',
        ]);
    }

    public function test_scenes_destroy_role_matrix_for_active_world(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);

        $cases = [
            'owner' => [$owner, 302],
            'co-gm' => [$coGm, 302],
            'admin' => [$admin, 302],
            'player' => [$player, 403],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $scene = Scene::factory()->create([
                'campaign_id' => $campaign->id,
                'created_by' => $owner->id,
                'title' => 'A3 Scene Destroy '.$suffix,
                'slug' => 'a3-scene-destroy-'.$suffix,
                'status' => 'open',
                'allow_ooc' => true,
                'mood' => 'neutral',
            ]);

            $response = $this->actingAs($actor)->delete(route('campaigns.scenes.destroy', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]));

            if ($expectedStatus === 302) {
                $response->assertRedirect(route('campaigns.show', [
                    'world' => $campaign->world,
                    'campaign' => $campaign,
                ]));
                $this->assertDatabaseMissing('scenes', [
                    'id' => $scene->id,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseHas('scenes', [
                'id' => $scene->id,
                'slug' => 'a3-scene-destroy-'.$suffix,
            ]);
        }
    }

    public function test_scenes_destroy_co_gm_negative_scope_cases_same_world_and_foreign_world(): void
    {
        [$campaign, , $coGm] = $this->seedCampaignRoleMatrix(worldActive: true);
        $foreignOwner = User::factory()->gm()->create();

        $sameWorldForeignCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'world_id' => $campaign->world_id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $sameWorldForeignScene = Scene::factory()->create([
            'campaign_id' => $sameWorldForeignCampaign->id,
            'created_by' => $foreignOwner->id,
            'title' => 'A3 CoGM Destroy Same World',
            'slug' => 'a3-cogm-destroy-same-world',
            'status' => 'open',
            'allow_ooc' => true,
            'mood' => 'neutral',
        ]);

        $this->actingAs($coGm)->delete(route('campaigns.scenes.destroy', [
            'world' => $campaign->world,
            'campaign' => $sameWorldForeignCampaign,
            'scene' => $sameWorldForeignScene,
        ]))->assertForbidden();

        $this->assertDatabaseHas('scenes', [
            'id' => $sameWorldForeignScene->id,
            'slug' => 'a3-cogm-destroy-same-world',
        ]);

        $foreignWorld = World::factory()->create([
            'slug' => 'a3-scene-destroy-foreign-world',
            'is_active' => true,
            'position' => -393,
        ]);
        $foreignWorldCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'world_id' => $foreignWorld->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $foreignWorldScene = Scene::factory()->create([
            'campaign_id' => $foreignWorldCampaign->id,
            'created_by' => $foreignOwner->id,
            'title' => 'A3 CoGM Destroy Foreign World',
            'slug' => 'a3-cogm-destroy-foreign-world',
            'status' => 'open',
            'allow_ooc' => true,
            'mood' => 'neutral',
        ]);

        $this->actingAs($coGm)->delete(route('campaigns.scenes.destroy', [
            'world' => $foreignWorld,
            'campaign' => $foreignWorldCampaign,
            'scene' => $foreignWorldScene,
        ]))->assertForbidden();

        $this->assertDatabaseHas('scenes', [
            'id' => $foreignWorldScene->id,
            'slug' => 'a3-cogm-destroy-foreign-world',
        ]);
    }

    public function test_campaigns_update_role_matrix_and_world_guards(): void
    {
        [$baseCampaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);
        $world = $baseCampaign->world()->firstOrFail();
        $foreignGm = User::factory()->gm()->create();

        $cases = [
            'owner' => [$owner, 302],
            'co-gm' => [$coGm, 302],
            'admin' => [$admin, 302],
            'foreign-gm' => [$foreignGm, 302],
            'player' => [$player, 403],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $campaign = Campaign::factory()->create([
                'owner_id' => $owner->id,
                'world_id' => $world->id,
                'title' => 'A3 Campaign Update Baseline '.$suffix,
                'slug' => 'a3-campaign-update-base-'.$suffix,
                'status' => 'active',
                'is_public' => false,
            ]);
            $this->acceptInvitation($campaign, $coGm, CampaignInvitation::ROLE_CO_GM, $owner);
            $this->acceptInvitation($campaign, $player, CampaignInvitation::ROLE_PLAYER, $owner);

            $updatedTitle = 'A3 Campaign Update '.$suffix;
            $updatedSlug = 'a3-campaign-update-'.$suffix;

            $response = $this->actingAs($actor)->patch(route('campaigns.update', [
                'world' => $world,
                'campaign' => $campaign,
            ]), $this->campaignUpdatePayload($updatedSlug, $updatedTitle));

            if ($expectedStatus === 302) {
                $response->assertRedirect(route('campaigns.show', [
                    'world' => $world,
                    'campaign' => $campaign,
                ]));
                $this->assertDatabaseHas('campaigns', [
                    'id' => $campaign->id,
                    'slug' => $updatedSlug,
                    'title' => $updatedTitle,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseHas('campaigns', [
                'id' => $campaign->id,
                'slug' => 'a3-campaign-update-base-'.$suffix,
                'title' => 'A3 Campaign Update Baseline '.$suffix,
            ]);
        }

        $foreignWorld = World::factory()->create([
            'slug' => 'a3-campaign-update-fremd-aktiv',
            'is_active' => true,
            'position' => -394,
        ]);
        $guardCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $world->id,
            'title' => 'A3 Campaign Update Guard',
            'slug' => 'a3-campaign-update-guard',
            'status' => 'active',
            'is_public' => false,
        ]);

        $this->actingAs($owner)->patch(route('campaigns.update', [
            'world' => $foreignWorld,
            'campaign' => $guardCampaign,
        ]), $this->campaignUpdatePayload(
            slug: 'a3-campaign-update-guard-blocked',
            title: 'A3 Campaign Update Guard Blocked',
        ))->assertNotFound();

        $this->assertDatabaseHas('campaigns', [
            'id' => $guardCampaign->id,
            'slug' => 'a3-campaign-update-guard',
            'title' => 'A3 Campaign Update Guard',
        ]);

        [$inactiveCampaign, $inactiveOwner] = $this->seedCampaignRoleMatrix(worldActive: false);

        $this->actingAs($inactiveOwner)->patch(route('campaigns.update', [
            'world' => $inactiveCampaign->world,
            'campaign' => $inactiveCampaign,
        ]), $this->campaignUpdatePayload(
            slug: 'a3-campaign-inactive-blocked',
            title: 'A3 Campaign Inactive Blocked',
        ))->assertNotFound();

        $this->assertDatabaseHas('campaigns', [
            'id' => $inactiveCampaign->id,
            'slug' => $inactiveCampaign->slug,
        ]);
    }

    public function test_campaigns_update_co_gm_negative_scope_cases_same_world_and_foreign_world(): void
    {
        [$campaign, , $coGm] = $this->seedCampaignRoleMatrix(worldActive: true);
        $foreignOwner = User::factory()->gm()->create();

        $sameWorldForeignCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'world_id' => $campaign->world_id,
            'title' => 'A3 CoGM Campaign Update Same World',
            'slug' => 'a3-cogm-campaign-update-same-world',
            'status' => 'active',
            'is_public' => false,
        ]);

        $this->actingAs($coGm)->patch(route('campaigns.update', [
            'world' => $campaign->world,
            'campaign' => $sameWorldForeignCampaign,
        ]), $this->campaignUpdatePayload(
            slug: 'a3-cogm-campaign-update-same-world-blocked',
            title: 'A3 CoGM Campaign Update Same World Blocked',
        ))->assertForbidden();

        $this->assertDatabaseHas('campaigns', [
            'id' => $sameWorldForeignCampaign->id,
            'slug' => 'a3-cogm-campaign-update-same-world',
            'title' => 'A3 CoGM Campaign Update Same World',
        ]);

        $foreignWorld = World::factory()->create([
            'slug' => 'a3-campaign-update-foreign-world',
            'is_active' => true,
            'position' => -395,
        ]);
        $foreignWorldCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'world_id' => $foreignWorld->id,
            'title' => 'A3 CoGM Campaign Update Foreign World',
            'slug' => 'a3-cogm-campaign-update-foreign-world',
            'status' => 'active',
            'is_public' => false,
        ]);

        $this->actingAs($coGm)->patch(route('campaigns.update', [
            'world' => $foreignWorld,
            'campaign' => $foreignWorldCampaign,
        ]), $this->campaignUpdatePayload(
            slug: 'a3-cogm-campaign-update-foreign-world-blocked',
            title: 'A3 CoGM Campaign Update Foreign World Blocked',
        ))->assertForbidden();

        $this->assertDatabaseHas('campaigns', [
            'id' => $foreignWorldCampaign->id,
            'slug' => 'a3-cogm-campaign-update-foreign-world',
            'title' => 'A3 CoGM Campaign Update Foreign World',
        ]);
    }

    public function test_campaigns_destroy_role_matrix_and_world_guards(): void
    {
        [$baseCampaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);
        $world = $baseCampaign->world()->firstOrFail();
        $foreignGm = User::factory()->gm()->create();

        $cases = [
            'owner' => [$owner, 302],
            'admin' => [$admin, 302],
            'foreign-gm' => [$foreignGm, 302],
            'co-gm' => [$coGm, 403],
            'player' => [$player, 403],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $campaign = Campaign::factory()->create([
                'owner_id' => $owner->id,
                'world_id' => $world->id,
                'title' => 'A3 Campaign Destroy '.$suffix,
                'slug' => 'a3-campaign-destroy-'.$suffix,
                'status' => 'active',
                'is_public' => false,
            ]);
            $this->acceptInvitation($campaign, $coGm, CampaignInvitation::ROLE_CO_GM, $owner);
            $this->acceptInvitation($campaign, $player, CampaignInvitation::ROLE_PLAYER, $owner);

            $response = $this->actingAs($actor)->delete(route('campaigns.destroy', [
                'world' => $world,
                'campaign' => $campaign,
            ]));

            if ($expectedStatus === 302) {
                $response->assertRedirect(route('campaigns.index', [
                    'world' => $world,
                ]));
                $this->assertDatabaseMissing('campaigns', [
                    'id' => $campaign->id,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseHas('campaigns', [
                'id' => $campaign->id,
                'slug' => 'a3-campaign-destroy-'.$suffix,
            ]);
        }

        $foreignWorld = World::factory()->create([
            'slug' => 'a3-campaign-destroy-fremd-aktiv',
            'is_active' => true,
            'position' => -396,
        ]);
        $guardCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $world->id,
            'title' => 'A3 Campaign Destroy Guard',
            'slug' => 'a3-campaign-destroy-guard',
            'status' => 'active',
            'is_public' => false,
        ]);

        $this->actingAs($owner)->delete(route('campaigns.destroy', [
            'world' => $foreignWorld,
            'campaign' => $guardCampaign,
        ]))->assertNotFound();

        $this->assertDatabaseHas('campaigns', [
            'id' => $guardCampaign->id,
            'slug' => 'a3-campaign-destroy-guard',
        ]);

        [$inactiveCampaign, $inactiveOwner] = $this->seedCampaignRoleMatrix(worldActive: false);

        $this->actingAs($inactiveOwner)->delete(route('campaigns.destroy', [
            'world' => $inactiveCampaign->world,
            'campaign' => $inactiveCampaign,
        ]))->assertNotFound();

        $this->assertDatabaseHas('campaigns', [
            'id' => $inactiveCampaign->id,
            'slug' => $inactiveCampaign->slug,
        ]);
    }

    public function test_campaigns_destroy_co_gm_negative_scope_cases_same_world_and_foreign_world(): void
    {
        [$campaign, , $coGm] = $this->seedCampaignRoleMatrix(worldActive: true);
        $foreignOwner = User::factory()->gm()->create();

        $sameWorldForeignCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'world_id' => $campaign->world_id,
            'title' => 'A3 CoGM Campaign Destroy Same World',
            'slug' => 'a3-cogm-campaign-destroy-same-world',
            'status' => 'active',
            'is_public' => false,
        ]);

        $this->actingAs($coGm)->delete(route('campaigns.destroy', [
            'world' => $campaign->world,
            'campaign' => $sameWorldForeignCampaign,
        ]))->assertForbidden();

        $this->assertDatabaseHas('campaigns', [
            'id' => $sameWorldForeignCampaign->id,
            'slug' => 'a3-cogm-campaign-destroy-same-world',
        ]);

        $foreignWorld = World::factory()->create([
            'slug' => 'a3-campaign-destroy-foreign-world',
            'is_active' => true,
            'position' => -397,
        ]);
        $foreignWorldCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'world_id' => $foreignWorld->id,
            'title' => 'A3 CoGM Campaign Destroy Foreign World',
            'slug' => 'a3-cogm-campaign-destroy-foreign-world',
            'status' => 'active',
            'is_public' => false,
        ]);

        $this->actingAs($coGm)->delete(route('campaigns.destroy', [
            'world' => $foreignWorld,
            'campaign' => $foreignWorldCampaign,
        ]))->assertForbidden();

        $this->assertDatabaseHas('campaigns', [
            'id' => $foreignWorldCampaign->id,
            'slug' => 'a3-cogm-campaign-destroy-foreign-world',
        ]);
    }

    public function test_posts_store_role_matrix_and_world_guards(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $cases = [
            'owner' => [$owner, 302],
            'co-gm' => [$coGm, 302],
            'admin' => [$admin, 302],
            'player' => [$player, 302],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $content = 'A3 Post Store '.$suffix.' '.str_repeat('X', 20);

            $response = $this->actingAs($actor)->post(route('campaigns.scenes.posts.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), $this->postStorePayload($content));

            if ($expectedStatus === 302) {
                $response->assertRedirectContains('/campaigns/'.$campaign->id.'/scenes/'.$scene->id);
                $this->assertDatabaseHas('posts', [
                    'scene_id' => $scene->id,
                    'user_id' => $actor->id,
                    'content' => $content,
                    'post_type' => 'ooc',
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseMissing('posts', [
                'scene_id' => $scene->id,
                'user_id' => $actor->id,
                'content' => $content,
            ]);
        }

        $foreignWorld = World::factory()->create([
            'slug' => 'a3-post-store-fremd-aktiv',
            'is_active' => true,
            'position' => -398,
        ]);
        $guardContent = 'A3 Post Store World Guard '.str_repeat('G', 20);

        $this->actingAs($owner)->post(route('campaigns.scenes.posts.store', [
            'world' => $foreignWorld,
            'campaign' => $campaign,
            'scene' => $scene,
        ]), $this->postStorePayload($guardContent))->assertNotFound();

        $this->assertDatabaseMissing('posts', [
            'scene_id' => $scene->id,
            'content' => $guardContent,
        ]);

        [$inactiveCampaign, $inactiveOwner] = $this->seedCampaignRoleMatrix(worldActive: false);
        $inactiveScene = Scene::factory()->create([
            'campaign_id' => $inactiveCampaign->id,
            'created_by' => $inactiveOwner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $inactiveContent = 'A3 Post Store Inactive '.str_repeat('I', 20);

        $this->actingAs($inactiveOwner)->post(route('campaigns.scenes.posts.store', [
            'world' => $inactiveCampaign->world,
            'campaign' => $inactiveCampaign,
            'scene' => $inactiveScene,
        ]), $this->postStorePayload($inactiveContent))->assertNotFound();

        $this->assertDatabaseMissing('posts', [
            'scene_id' => $inactiveScene->id,
            'content' => $inactiveContent,
        ]);
    }

    public function test_posts_store_co_gm_negative_scope_cases_same_world_and_foreign_world(): void
    {
        [$campaign, , $coGm] = $this->seedCampaignRoleMatrix(worldActive: true);
        $foreignOwner = User::factory()->gm()->create();

        $sameWorldForeignCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'world_id' => $campaign->world_id,
            'status' => 'active',
            'is_public' => false,
        ]);
        $sameWorldForeignScene = Scene::factory()->create([
            'campaign_id' => $sameWorldForeignCampaign->id,
            'created_by' => $foreignOwner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $sameWorldContent = 'A3 CoGM Post Store Same World '.str_repeat('S', 20);

        $this->actingAs($coGm)->post(route('campaigns.scenes.posts.store', [
            'world' => $campaign->world,
            'campaign' => $sameWorldForeignCampaign,
            'scene' => $sameWorldForeignScene,
        ]), $this->postStorePayload($sameWorldContent))->assertForbidden();

        $this->assertDatabaseMissing('posts', [
            'scene_id' => $sameWorldForeignScene->id,
            'content' => $sameWorldContent,
        ]);

        $foreignWorld = World::factory()->create([
            'slug' => 'a3-post-store-foreign-world',
            'is_active' => true,
            'position' => -399,
        ]);
        $foreignWorldCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'world_id' => $foreignWorld->id,
            'status' => 'active',
            'is_public' => false,
        ]);
        $foreignWorldScene = Scene::factory()->create([
            'campaign_id' => $foreignWorldCampaign->id,
            'created_by' => $foreignOwner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $foreignWorldContent = 'A3 CoGM Post Store Foreign World '.str_repeat('F', 20);

        $this->actingAs($coGm)->post(route('campaigns.scenes.posts.store', [
            'world' => $foreignWorld,
            'campaign' => $foreignWorldCampaign,
            'scene' => $foreignWorldScene,
        ]), $this->postStorePayload($foreignWorldContent))->assertForbidden();

        $this->assertDatabaseMissing('posts', [
            'scene_id' => $foreignWorldScene->id,
            'content' => $foreignWorldContent,
        ]);
    }

    public function test_posts_moderate_hx_request_response_boundaries_and_guards(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $cases = [
            'owner' => [$owner, 200],
            'co-gm' => [$coGm, 200],
            'admin' => [$admin, 200],
            'player' => [$player, 403],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $post = Post::factory()->create([
                'scene_id' => $scene->id,
                'user_id' => $player->id,
                'content' => 'A3 HX Moderate '.$suffix,
                'moderation_status' => 'pending',
                'approved_at' => null,
                'approved_by' => null,
            ]);

            $response = $this->actingAs($actor)
                ->withHeaders([
                    'HX-Request' => 'true',
                    'HX-Target' => 'post-'.$post->id,
                ])
                ->patch(route('posts.moderate', [
                    'world' => $campaign->world,
                    'post' => $post,
                ]), $this->postModerationPayload('approved', 'A3 HX '.$suffix));

            if ($expectedStatus === 200) {
                $response->assertOk();
                $response->assertSeeText('A3 HX Moderate '.$suffix);
                $this->assertDatabaseHas('posts', [
                    'id' => $post->id,
                    'moderation_status' => 'approved',
                    'approved_by' => $actor->id,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseHas('posts', [
                'id' => $post->id,
                'moderation_status' => 'pending',
                'approved_by' => null,
            ]);
        }

        $boundaryPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'content' => 'A3 HX Moderate Boundary',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $this->actingAs($owner)
            ->withHeaders([
                'HX-Request' => 'true',
                'HX-Target' => 'thread-page',
            ])
            ->from(route('campaigns.scenes.show', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]))
            ->patch(route('posts.moderate', [
                'world' => $campaign->world,
                'post' => $boundaryPost,
            ]), $this->postModerationPayload('approved', 'A3 HX boundary'))
            ->assertRedirect(route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]));

        $this->assertDatabaseHas('posts', [
            'id' => $boundaryPost->id,
            'moderation_status' => 'approved',
            'approved_by' => $owner->id,
        ]);

        $foreignWorld = World::factory()->create([
            'slug' => 'a3-posts-moderate-hx-foreign-world',
            'is_active' => true,
            'position' => -400,
        ]);
        $guardPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'content' => 'A3 HX Moderate Guard',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $this->actingAs($owner)
            ->withHeaders([
                'HX-Request' => 'true',
                'HX-Target' => 'post-'.$guardPost->id,
            ])
            ->patch(route('posts.moderate', [
                'world' => $foreignWorld,
                'post' => $guardPost,
            ]), $this->postModerationPayload('approved', 'A3 HX wrong world'))
            ->assertNotFound();

        $inactiveWorld = World::factory()->create([
            'slug' => 'a3-posts-moderate-hx-inaktiv',
            'is_active' => false,
            'position' => -401,
        ]);

        $this->actingAs($owner)
            ->withHeaders([
                'HX-Request' => 'true',
                'HX-Target' => 'post-'.$guardPost->id,
            ])
            ->patch(route('posts.moderate', [
                'world' => $inactiveWorld,
                'post' => $guardPost,
            ]), $this->postModerationPayload('approved', 'A3 HX inactive world'))
            ->assertNotFound();

        $this->assertDatabaseHas('posts', [
            'id' => $guardPost->id,
            'moderation_status' => 'pending',
            'approved_by' => null,
        ]);
    }

    public function test_posts_pin_unpin_hx_request_response_boundaries_and_guards(): void
    {
        [$campaign, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $cases = [
            'owner' => [$owner, 200],
            'co-gm' => [$coGm, 200],
            'admin' => [$admin, 200],
            'player' => [$player, 403],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $post = Post::factory()->create([
                'scene_id' => $scene->id,
                'user_id' => $player->id,
                'content' => 'A3 HX Pin '.$suffix,
                'is_pinned' => false,
                'pinned_by' => null,
                'pinned_at' => null,
            ]);

            $pinResponse = $this->actingAs($actor)
                ->withHeaders(['HX-Request' => 'true'])
                ->patch(route('posts.pin', [
                    'world' => $campaign->world,
                    'post' => $post,
                ]));

            if ($expectedStatus === 200) {
                $pinResponse->assertOk();
                $pinResponse->assertSeeText('A3 HX Pin '.$suffix);
                $this->assertDatabaseHas('posts', [
                    'id' => $post->id,
                    'is_pinned' => true,
                    'pinned_by' => $actor->id,
                ]);

                $unpinResponse = $this->actingAs($actor)
                    ->withHeaders(['HX-Request' => 'true'])
                    ->patch(route('posts.unpin', [
                        'world' => $campaign->world,
                        'post' => $post,
                    ]));

                $unpinResponse->assertOk();
                $unpinResponse->assertSeeText('A3 HX Pin '.$suffix);
                $this->assertDatabaseHas('posts', [
                    'id' => $post->id,
                    'is_pinned' => false,
                    'pinned_by' => null,
                ]);

                continue;
            }

            $pinResponse->assertStatus($expectedStatus);
            $this->assertDatabaseHas('posts', [
                'id' => $post->id,
                'is_pinned' => false,
                'pinned_by' => null,
            ]);
        }

        $guardPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'content' => 'A3 HX Pin Guard',
            'is_pinned' => false,
            'pinned_by' => null,
            'pinned_at' => null,
        ]);
        $foreignWorld = World::factory()->create([
            'slug' => 'a3-posts-pin-hx-foreign-world',
            'is_active' => true,
            'position' => -402,
        ]);
        $inactiveWorld = World::factory()->create([
            'slug' => 'a3-posts-pin-hx-inaktiv',
            'is_active' => false,
            'position' => -403,
        ]);

        $this->actingAs($owner)
            ->withHeaders(['HX-Request' => 'true'])
            ->patch(route('posts.pin', [
                'world' => $foreignWorld,
                'post' => $guardPost,
            ]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->withHeaders(['HX-Request' => 'true'])
            ->patch(route('posts.unpin', [
                'world' => $inactiveWorld,
                'post' => $guardPost,
            ]))
            ->assertNotFound();

        $this->assertDatabaseHas('posts', [
            'id' => $guardPost->id,
            'is_pinned' => false,
            'pinned_by' => null,
        ]);
    }

    public function test_posts_moderate_pin_unpin_co_gm_negative_scope_cases_same_world_and_foreign_world(): void
    {
        [$campaign, , $coGm, $player] = $this->seedCampaignRoleMatrix(worldActive: true);
        $foreignOwner = User::factory()->gm()->create();

        $sameWorldForeignCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'world_id' => $campaign->world_id,
            'status' => 'active',
            'is_public' => false,
        ]);
        $sameWorldForeignScene = Scene::factory()->create([
            'campaign_id' => $sameWorldForeignCampaign->id,
            'created_by' => $foreignOwner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $sameWorldPost = Post::factory()->create([
            'scene_id' => $sameWorldForeignScene->id,
            'user_id' => $player->id,
            'content' => 'A3 CoGM HX Scope Same World',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
            'is_pinned' => false,
            'pinned_by' => null,
            'pinned_at' => null,
        ]);

        $this->actingAs($coGm)
            ->withHeaders(['HX-Request' => 'true', 'HX-Target' => 'post-'.$sameWorldPost->id])
            ->patch(route('posts.moderate', [
                'world' => $campaign->world,
                'post' => $sameWorldPost,
            ]), $this->postModerationPayload('approved', 'A3 CoGM blocked same world'))
            ->assertForbidden();

        $this->actingAs($coGm)
            ->withHeaders(['HX-Request' => 'true'])
            ->patch(route('posts.pin', [
                'world' => $campaign->world,
                'post' => $sameWorldPost,
            ]))
            ->assertForbidden();

        $this->actingAs($coGm)
            ->withHeaders(['HX-Request' => 'true'])
            ->patch(route('posts.unpin', [
                'world' => $campaign->world,
                'post' => $sameWorldPost,
            ]))
            ->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $sameWorldPost->id,
            'moderation_status' => 'pending',
            'approved_by' => null,
            'is_pinned' => false,
            'pinned_by' => null,
        ]);

        $foreignWorld = World::factory()->create([
            'slug' => 'a3-posts-hx-scope-foreign-world',
            'is_active' => true,
            'position' => -404,
        ]);
        $foreignWorldCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'world_id' => $foreignWorld->id,
            'status' => 'active',
            'is_public' => false,
        ]);
        $foreignWorldScene = Scene::factory()->create([
            'campaign_id' => $foreignWorldCampaign->id,
            'created_by' => $foreignOwner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $foreignWorldPost = Post::factory()->create([
            'scene_id' => $foreignWorldScene->id,
            'user_id' => $player->id,
            'content' => 'A3 CoGM HX Scope Foreign World',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
            'is_pinned' => false,
            'pinned_by' => null,
            'pinned_at' => null,
        ]);

        $this->actingAs($coGm)
            ->withHeaders(['HX-Request' => 'true', 'HX-Target' => 'post-'.$foreignWorldPost->id])
            ->patch(route('posts.moderate', [
                'world' => $foreignWorld,
                'post' => $foreignWorldPost,
            ]), $this->postModerationPayload('approved', 'A3 CoGM blocked foreign world'))
            ->assertForbidden();

        $this->actingAs($coGm)
            ->withHeaders(['HX-Request' => 'true'])
            ->patch(route('posts.pin', [
                'world' => $foreignWorld,
                'post' => $foreignWorldPost,
            ]))
            ->assertForbidden();

        $this->actingAs($coGm)
            ->withHeaders(['HX-Request' => 'true'])
            ->patch(route('posts.unpin', [
                'world' => $foreignWorld,
                'post' => $foreignWorldPost,
            ]))
            ->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $foreignWorldPost->id,
            'moderation_status' => 'pending',
            'approved_by' => null,
            'is_pinned' => false,
            'pinned_by' => null,
        ]);
    }

    /**
     * @return array{Campaign, User, User, User, User, User}
     */
    private function seedCampaignRoleMatrix(bool $worldActive): array
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();
        $player = User::factory()->create();
        $outsider = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $world = World::factory()->create([
            'slug' => $worldActive ? 'a3-aktive-welt' : 'a3-inaktive-kampagnenwelt',
            'is_active' => $worldActive,
            'position' => -300,
        ]);

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $world->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        $this->acceptInvitation($campaign, $coGm, CampaignInvitation::ROLE_CO_GM, $owner);
        $this->acceptInvitation($campaign, $player, CampaignInvitation::ROLE_PLAYER, $owner);

        return [$campaign, $owner, $coGm, $player, $outsider, $admin];
    }

    private function acceptInvitation(Campaign $campaign, User $user, string $role, User $inviter): void
    {
        CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'invited_by' => $inviter->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => $role,
            'accepted_at' => now(),
            'responded_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function scenePayload(string $slug, string $title): array
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'summary' => 'A3 Matrix Szenen-Create',
            'status' => 'open',
            'mood' => 'neutral',
            'position' => 1,
            'allow_ooc' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gmProgressionPayload(
        int $campaignId,
        int $sceneId,
        int $characterId,
        string $reason
    ): array {
        return [
            'campaign_id' => $campaignId,
            'scene_id' => $sceneId,
            'event_mode' => 'milestone',
            'reason' => $reason,
            'awards' => [[
                'character_id' => $characterId,
                'xp_delta' => 30,
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function inventoryQuickActionPayload(int $characterId, array $overrides = []): array
    {
        return array_merge([
            'inventory_action_character_id' => $characterId,
            'inventory_action_type' => 'add',
            'inventory_action_item' => 'Heiltrank',
            'inventory_action_quantity' => 2,
            'inventory_action_equipped' => '0',
            'inventory_action_note' => 'A3 Matrix',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function sceneSubscriptionBulkPayload(string $action, string $status, string $search): array
    {
        return [
            'bulk_action' => $action,
            'status' => $status,
            'q' => $search,
        ];
    }

    /**
     * @param  list<int>  $postIds
     * @return array<string, mixed>
     */
    private function gmBulkModerationPayload(string $moderationStatus, array $postIds): array
    {
        return [
            'status' => 'all',
            'q' => '',
            'moderation_status' => $moderationStatus,
            'moderation_note' => 'A3 Matrix Bulk',
            'post_ids' => $postIds,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignInvitationPayload(string $email, string $role): array
    {
        return [
            'email' => $email,
            'role' => $role,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function postUpdatePayload(string $content): array
    {
        return [
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => $content,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sceneUpdatePayload(string $slug, string $title): array
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'summary' => 'A3 Matrix Scene Update',
            'description' => 'A3 Matrix Scene Update Description',
            'status' => 'open',
            'mood' => 'neutral',
            'position' => 2,
            'allow_ooc' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignUpdatePayload(string $slug, string $title): array
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'summary' => 'A3 Matrix Campaign Update',
            'lore' => 'A3 Matrix Campaign Update Lore',
            'status' => 'active',
            'is_public' => false,
            'requires_post_moderation' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function postStorePayload(string $content): array
    {
        return [
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => $content,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function postModerationPayload(string $status, string $note): array
    {
        return [
            'moderation_status' => $status,
            'moderation_note' => $note,
        ];
    }
}
