<?php

namespace Tests\Feature\AuthorizationWorldContext;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Models\World;

class AuthorizationWorldContextMutationScopeTest extends AuthorizationWorldContextMutationTestCase
{
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

}
