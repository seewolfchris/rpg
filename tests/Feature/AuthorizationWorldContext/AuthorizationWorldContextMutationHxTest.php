<?php

namespace Tests\Feature\AuthorizationWorldContext;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;

class AuthorizationWorldContextMutationHxTest extends AuthorizationWorldContextMutationTestCase
{
    public function test_gm_moderation_bulk_update_hx_role_matrix_and_response_boundaries(): void
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
                'content' => 'A3 HX Bulk Moderation '.$suffix,
                'moderation_status' => 'pending',
                'approved_at' => null,
                'approved_by' => null,
            ]);

            $response = $this->actingAs($actor)
                ->withHeaders([
                    'HX-Request' => 'true',
                    'HX-Target' => 'thread-page',
                ])
                ->patch(route('gm.moderation.bulk-update', [
                    'world' => $campaign->world,
                ]), $this->gmBulkModerationPayload(
                    moderationStatus: 'approved',
                    postIds: [(int) $post->id],
                    sceneId: (int) $scene->id,
                ));

            if ($expectedStatus === 200) {
                $response->assertOk();
                $response->assertSeeText('A3 HX Bulk Moderation '.$suffix);
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
            'content' => 'A3 HX Bulk Redirect Boundary',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $this->actingAs($owner)
            ->withHeaders([
                'HX-Request' => 'true',
                'HX-Target' => 'thread-page',
            ])
            ->from(route('gm.moderation.index', ['world' => $campaign->world, 'status' => 'all']))
            ->patch(route('gm.moderation.bulk-update', [
                'world' => $campaign->world,
            ]), $this->gmBulkModerationPayload(
                moderationStatus: 'approved',
                postIds: [(int) $boundaryPost->id],
                sceneId: null,
            ))
            ->assertRedirect(route('gm.moderation.index', [
                'world' => $campaign->world,
                'status' => 'all',
            ]));

        $this->assertDatabaseHas('posts', [
            'id' => $boundaryPost->id,
            'moderation_status' => 'approved',
            'approved_by' => $owner->id,
        ]);
    }

    public function test_gm_moderation_bulk_update_hx_world_guards_and_co_gm_negative_scope(): void
    {
        [$campaign, $owner, $coGm, $player] = $this->seedCampaignRoleMatrix(worldActive: true);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
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
            'content' => 'A3 HX Bulk CoGM Same World',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $this->actingAs($coGm)
            ->withHeaders([
                'HX-Request' => 'true',
                'HX-Target' => 'thread-page',
            ])
            ->patch(route('gm.moderation.bulk-update', [
                'world' => $campaign->world,
            ]), $this->gmBulkModerationPayload(
                moderationStatus: 'approved',
                postIds: [(int) $sameWorldForeignPost->id],
                sceneId: (int) $sameWorldForeignScene->id,
            ))
            ->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $sameWorldForeignPost->id,
            'moderation_status' => 'pending',
            'approved_by' => null,
        ]);

        $foreignWorld = World::factory()->create([
            'slug' => 'a3-hx-bulk-moderation-foreign-world',
            'is_active' => true,
            'position' => -408,
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
            'content' => 'A3 HX Bulk CoGM Foreign World',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $this->actingAs($coGm)
            ->withHeaders([
                'HX-Request' => 'true',
                'HX-Target' => 'thread-page',
            ])
            ->patch(route('gm.moderation.bulk-update', [
                'world' => $foreignWorld,
            ]), $this->gmBulkModerationPayload(
                moderationStatus: 'approved',
                postIds: [(int) $foreignWorldPost->id],
                sceneId: (int) $foreignWorldScene->id,
            ))
            ->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $foreignWorldPost->id,
            'moderation_status' => 'pending',
            'approved_by' => null,
        ]);

        $this->actingAs($owner)
            ->withHeaders([
                'HX-Request' => 'true',
                'HX-Target' => 'thread-page',
            ])
            ->patch(route('gm.moderation.bulk-update', [
                'world' => $foreignWorld,
            ]), $this->gmBulkModerationPayload(
                moderationStatus: 'approved',
                postIds: [],
                sceneId: (int) $scene->id,
            ))
            ->assertNotFound();

        $inactiveWorld = World::factory()->create([
            'slug' => 'a3-hx-bulk-moderation-inaktiv',
            'is_active' => false,
            'position' => -409,
        ]);

        $this->actingAs($owner)
            ->withHeaders([
                'HX-Request' => 'true',
                'HX-Target' => 'thread-page',
            ])
            ->patch(route('gm.moderation.bulk-update', [
                'world' => $inactiveWorld,
            ]), $this->gmBulkModerationPayload(
                moderationStatus: 'approved',
                postIds: [],
                sceneId: (int) $scene->id,
            ))
            ->assertNotFound();
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
}
