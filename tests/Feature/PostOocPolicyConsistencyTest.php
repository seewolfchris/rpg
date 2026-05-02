<?php

namespace Tests\Feature;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostOocPolicyConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_cannot_store_or_update_ooc_when_scene_disables_ooc(): void
    {
        $owner = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => false,
        ]);

        $this->actingAs($player)
            ->post(route('campaigns.scenes.posts.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), [
                'post_type' => 'ooc',
                'character_id' => null,
                'content_format' => 'plain',
                'content' => 'OOC-Post eines Spielers.',
            ])
            ->assertSessionHasErrors('post_type');

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Alter OOC-Inhalt.',
        ]);

        $this->actingAs($player)
            ->patch(route('posts.update', ['world' => $campaign->world, 'post' => $post]), [
                'post_type' => 'ooc',
                'character_id' => null,
                'content_format' => 'plain',
                'content' => 'Neuer OOC-Inhalt des Spielers.',
            ])
            ->assertSessionHasErrors('post_type');
    }

    public function test_gm_and_world_co_gm_can_store_and_update_ooc_when_scene_disables_ooc(): void
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => false,
        ]);

        $this->grantMembership($campaign, $coGm, CampaignMembershipRole::GM, $owner);

        $this->actingAs($owner)
            ->post(route('campaigns.scenes.posts.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), [
                'post_type' => 'ooc',
                'character_id' => null,
                'content_format' => 'plain',
                'content' => 'GM-OOC-Anweisung.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('posts', [
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
            'post_type' => 'ooc',
            'content' => 'GM-OOC-Anweisung.',
        ]);

        $this->actingAs($coGm)
            ->post(route('campaigns.scenes.posts.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]), [
                'post_type' => 'ooc',
                'character_id' => null,
                'content_format' => 'plain',
                'content' => 'Co-GM-OOC-Hinweis.',
            ])
            ->assertSessionHasNoErrors();

        $coGmPost = Post::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $coGm->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($coGm)
            ->patch(route('posts.update', ['world' => $campaign->world, 'post' => $coGmPost]), [
                'post_type' => 'ooc',
                'character_id' => null,
                'content_format' => 'plain',
                'content' => 'Co-GM-OOC-Hinweis aktualisiert.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('posts', [
            'id' => $coGmPost->id,
            'post_type' => 'ooc',
            'content' => 'Co-GM-OOC-Hinweis aktualisiert.',
        ]);
    }

    private function grantMembership(
        Campaign $campaign,
        User $member,
        CampaignMembershipRole $role,
        User $assigner,
    ): void
    {
        CampaignMembership::query()->updateOrCreate(
            [
                'campaign_id' => (int) $campaign->id,
                'user_id' => (int) $member->id,
            ],
            [
                'role' => $role->value,
                'assigned_by' => (int) $assigner->id,
                'assigned_at' => now(),
            ]
        );
    }
}
