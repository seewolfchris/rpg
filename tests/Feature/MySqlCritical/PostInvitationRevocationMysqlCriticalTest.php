<?php

namespace Tests\Feature\MySqlCritical;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignMembership;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('mysql-critical')]
class PostInvitationRevocationMysqlCriticalTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_revoked_keeps_posts_update_and_destroy_forbidden(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only critical test.');
        }

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
        CampaignMembership::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $player->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => (int) $gm->id,
            'assigned_at' => now(),
        ]);

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $player->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'mysql-critical baseline',
            'moderation_status' => 'pending',
        ]);

        $this->actingAs($gm)->delete(route('campaigns.invitations.destroy', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'invitation' => $invitation,
        ]))->assertRedirect(route('campaigns.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]));
        $this->assertDatabaseMissing('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $player->id,
        ]);

        $this->actingAs($player)->patch(route('posts.update', [
            'world' => $campaign->world,
            'post' => $post,
        ]), [
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'mysql-critical denied update',
        ])->assertForbidden();

        $this->actingAs($player)->delete(route('posts.destroy', [
            'world' => $campaign->world,
            'post' => $post,
        ]))->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'content' => 'mysql-critical baseline',
        ]);
    }
}
