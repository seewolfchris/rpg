<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostInvitationRevocationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_revoked_blocks_post_update_and_delete_for_former_participant(): void
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $invitation = $campaign->invitations()->create([
            'user_id' => $player->id,
            'invited_by' => $gm->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'A3 revoked-invitation baseline',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $this->actingAs($gm)->delete(route('campaigns.invitations.destroy', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'invitation' => $invitation,
        ]))->assertRedirect(route('campaigns.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]));

        $this->assertDatabaseMissing('campaign_invitations', [
            'id' => $invitation->id,
        ]);

        $this->actingAs($player)->patch(route('posts.update', [
            'world' => $campaign->world,
            'post' => $post,
        ]), [
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'A3 revoked update denied',
        ])->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'content' => 'A3 revoked-invitation baseline',
        ]);

        $this->actingAs($player)->delete(route('posts.destroy', [
            'world' => $campaign->world,
            'post' => $post,
        ]))->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
        ]);
    }
}

