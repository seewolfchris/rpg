<?php

namespace Tests\Feature\AuthorizationWorldContext;

use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;

class AuthorizationWorldContextMutationCoreTest extends AuthorizationWorldContextMutationTestCase
{
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

}
