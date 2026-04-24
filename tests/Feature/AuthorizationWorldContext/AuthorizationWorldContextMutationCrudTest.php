<?php

namespace Tests\Feature\AuthorizationWorldContext;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;

class AuthorizationWorldContextMutationCrudTest extends AuthorizationWorldContextMutationTestCase
{
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
            'admin' => [$admin, 403],
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

    public function test_posts_update_denies_unauthorized_actor_before_payload_validation(): void
    {
        [$campaign, $owner, , $player, $outsider] = $this->seedCampaignRoleMatrix(worldActive: true);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'A3 Post update boundary baseline',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $response = $this->actingAs($outsider)
            ->from(route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->patch(route('posts.update', [
                'world' => $campaign->world,
                'post' => $post,
            ]), [
                'post_type' => 'ic',
                'content_format' => 'plain',
                'content' => '',
            ]);

        $response->assertForbidden();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'content' => 'A3 Post update boundary baseline',
        ]);
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
            'admin' => [$admin, 403],
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
                $this->assertSoftDeleted('posts', [
                    'id' => $post->id,
                    'deleted_by' => $actor->id,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertNotSoftDeleted('posts', [
                'id' => $post->id,
            ]);
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

        $this->assertNotSoftDeleted('posts', [
            'id' => $sameWorldForeignPost->id,
        ]);
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

        $this->assertNotSoftDeleted('posts', [
            'id' => $foreignWorldPost->id,
        ]);
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
            'admin' => [$admin, 403],
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
            'admin' => [$admin, 403],
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
            'admin' => [$admin, 403],
            'foreign-gm' => [$foreignGm, 403],
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
            'admin' => [$admin, 403],
            'foreign-gm' => [$foreignGm, 403],
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

    public function test_campaigns_store_role_matrix_and_world_guards(): void
    {
        [, $owner, $coGm, $player, $outsider, $admin] = $this->seedCampaignRoleMatrix(worldActive: true);
        $foreignGm = User::factory()->gm()->create();
        $world = World::factory()->create([
            'slug' => 'a3-campaign-store-aktiv',
            'is_active' => true,
            'position' => -405,
        ]);

        $cases = [
            'owner-gm' => [$owner, 302],
            'foreign-gm' => [$foreignGm, 302],
            'admin' => [$admin, 302],
            'co-gm' => [$coGm, 403],
            'player' => [$player, 403],
            'outsider' => [$outsider, 403],
        ];

        foreach ($cases as $suffix => [$actor, $expectedStatus]) {
            $slug = 'a3-campaign-store-'.$suffix;
            $title = 'A3 Campaign Store '.$suffix;

            $response = $this->actingAs($actor)->post(route('campaigns.store', [
                'world' => $world,
            ]), $this->campaignStorePayload($slug, $title));

            if ($expectedStatus === 302) {
                $response->assertRedirectContains('/campaigns/');
                $this->assertDatabaseHas('campaigns', [
                    'slug' => $slug,
                    'title' => $title,
                    'owner_id' => $actor->id,
                    'world_id' => $world->id,
                ]);
                $campaignId = (int) Campaign::query()
                    ->where('slug', $slug)
                    ->value('id');
                $this->assertGreaterThan(0, $campaignId);
                $this->assertDatabaseHas('campaign_memberships', [
                    'campaign_id' => $campaignId,
                    'user_id' => $actor->id,
                    'role' => CampaignMembershipRole::GM->value,
                    'assigned_by' => $actor->id,
                ]);

                continue;
            }

            $response->assertStatus($expectedStatus);
            $this->assertDatabaseMissing('campaigns', [
                'slug' => $slug,
                'world_id' => $world->id,
            ]);
        }

        $inactiveWorld = World::factory()->create([
            'slug' => 'a3-campaign-store-inaktiv',
            'is_active' => false,
            'position' => -406,
        ]);

        $this->actingAs($owner)->post(route('campaigns.store', [
            'world' => $inactiveWorld,
        ]), $this->campaignStorePayload(
            slug: 'a3-campaign-store-inaktiv-blocked',
            title: 'A3 Campaign Store Inactive Blocked',
        ))->assertNotFound();

        $this->assertDatabaseMissing('campaigns', [
            'slug' => 'a3-campaign-store-inaktiv-blocked',
            'world_id' => $inactiveWorld->id,
        ]);
    }

    public function test_campaigns_store_co_gm_negative_cases_same_world_and_foreign_world(): void
    {
        [$campaign, , $coGm] = $this->seedCampaignRoleMatrix(worldActive: true);

        $sameWorldSlug = 'a3-cogm-campaign-store-same-world';
        $this->actingAs($coGm)->post(route('campaigns.store', [
            'world' => $campaign->world,
        ]), $this->campaignStorePayload(
            slug: $sameWorldSlug,
            title: 'A3 CoGM Campaign Store Same World',
        ))->assertForbidden();

        $this->assertDatabaseMissing('campaigns', [
            'slug' => $sameWorldSlug,
            'world_id' => $campaign->world_id,
        ]);

        $foreignWorld = World::factory()->create([
            'slug' => 'a3-campaign-store-foreign-world',
            'is_active' => true,
            'position' => -407,
        ]);
        $foreignWorldSlug = 'a3-cogm-campaign-store-foreign-world';

        $this->actingAs($coGm)->post(route('campaigns.store', [
            'world' => $foreignWorld,
        ]), $this->campaignStorePayload(
            slug: $foreignWorldSlug,
            title: 'A3 CoGM Campaign Store Foreign World',
        ))->assertForbidden();

        $this->assertDatabaseMissing('campaigns', [
            'slug' => $foreignWorldSlug,
            'world_id' => $foreignWorld->id,
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
            'admin' => [$admin, 403],
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

    public function test_posts_store_denies_unauthorized_actor_before_payload_validation(): void
    {
        [$campaign, $owner, , , $outsider] = $this->seedCampaignRoleMatrix(worldActive: true);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $response = $this->actingAs($outsider)
            ->from(route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]))
            ->post(route('campaigns.scenes.posts.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), [
                'post_type' => 'ic',
                'content_format' => 'plain',
                'content' => '',
            ]);

        $response->assertForbidden();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('posts', [
            'scene_id' => $scene->id,
            'user_id' => $outsider->id,
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

}
