<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
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
}
