<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\DiceRoll;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Support\ProbeRoller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GmModerationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_cannot_access_gm_moderation_queue(): void
    {
        $player = User::factory()->create([
            'role' => UserRole::PLAYER->value,
        ]);

        $response = $this->actingAs($player)->get(route('gm.moderation.index'));

        $response->assertForbidden();
    }

    public function test_co_gm_can_access_queue_only_for_assigned_campaigns(): void
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();
        $foreignOwner = User::factory()->gm()->create();
        $author = User::factory()->create();

        $ownCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $foreignCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        CampaignInvitation::query()->create([
            'campaign_id' => $ownCampaign->id,
            'user_id' => $coGm->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_CO_GM,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $ownScene = Scene::factory()->create([
            'campaign_id' => $ownCampaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $foreignScene = Scene::factory()->create([
            'campaign_id' => $foreignCampaign->id,
            'created_by' => $foreignOwner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        Post::factory()->create([
            'scene_id' => $ownScene->id,
            'user_id' => $author->id,
            'content' => 'COGM-EIGEN',
            'content_format' => 'plain',
            'post_type' => 'ic',
            'moderation_status' => 'pending',
        ]);
        Post::factory()->create([
            'scene_id' => $foreignScene->id,
            'user_id' => $author->id,
            'content' => 'COGM-FREMD',
            'content_format' => 'plain',
            'post_type' => 'ic',
            'moderation_status' => 'pending',
        ]);

        $response = $this->actingAs($coGm)->get(route('gm.moderation.index', [
            'status' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('COGM-EIGEN');
        $response->assertDontSee('COGM-FREMD');
    }

    public function test_co_gm_cannot_bulk_moderate_post_outside_assigned_campaigns(): void
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();
        $foreignOwner = User::factory()->gm()->create();
        $author = User::factory()->create();

        $ownCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $foreignCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        CampaignInvitation::query()->create([
            'campaign_id' => $ownCampaign->id,
            'user_id' => $coGm->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_CO_GM,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $foreignScene = Scene::factory()->create([
            'campaign_id' => $foreignCampaign->id,
            'created_by' => $foreignOwner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $foreignPost = Post::factory()->create([
            'scene_id' => $foreignScene->id,
            'user_id' => $author->id,
            'content' => 'AUSSERHALB-SCOPE',
            'content_format' => 'plain',
            'post_type' => 'ic',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $response = $this->actingAs($coGm)
            ->patch(route('gm.moderation.bulk-update'), [
                'status' => 'all',
                'q' => '',
                'moderation_status' => 'approved',
                'moderation_note' => 'Unberechtigter Versuch',
                'post_ids' => [$foreignPost->id],
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $foreignPost->id,
            'moderation_status' => 'pending',
            'approved_by' => null,
        ]);
    }

    public function test_gm_can_filter_queue_by_status_and_search_term(): void
    {
        $gm = User::factory()->gm()->create();
        $author = User::factory()->create();

        $campaignNorth = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'title' => 'Nordklinge',
            'status' => 'active',
            'is_public' => true,
        ]);
        $campaignSouth = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'title' => 'Suedwall',
            'status' => 'active',
            'is_public' => true,
        ]);

        $sceneNorth = Scene::factory()->create([
            'campaign_id' => $campaignNorth->id,
            'created_by' => $gm->id,
            'title' => 'Nordtor',
            'status' => 'open',
            'allow_ooc' => true,
        ]);
        $sceneSouth = Scene::factory()->create([
            'campaign_id' => $campaignSouth->id,
            'created_by' => $gm->id,
            'title' => 'Suedtor',
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        Post::factory()->create([
            'scene_id' => $sceneNorth->id,
            'user_id' => $author->id,
            'content_format' => 'plain',
            'post_type' => 'ic',
            'content' => 'PENDING-RABE',
            'moderation_status' => 'pending',
        ]);
        Post::factory()->create([
            'scene_id' => $sceneSouth->id,
            'user_id' => $author->id,
            'content_format' => 'plain',
            'post_type' => 'ic',
            'content' => 'APPROVED-WOLF',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $gm->id,
        ]);
        Post::factory()->create([
            'scene_id' => $sceneNorth->id,
            'user_id' => $author->id,
            'content_format' => 'plain',
            'post_type' => 'ic',
            'content' => 'REJECTED-NACHT',
            'moderation_status' => 'rejected',
        ]);

        $defaultResponse = $this->actingAs($gm)->get(route('gm.moderation.index'));

        $defaultResponse->assertOk();
        $defaultResponse->assertSee('PENDING-RABE');
        $defaultResponse->assertDontSee('APPROVED-WOLF');
        $defaultResponse->assertDontSee('REJECTED-NACHT');

        $approvedResponse = $this->actingAs($gm)->get(route('gm.moderation.index', [
            'status' => 'approved',
        ]));

        $approvedResponse->assertOk();
        $approvedResponse->assertSee('APPROVED-WOLF');
        $approvedResponse->assertDontSee('PENDING-RABE');

        $searchResponse = $this->actingAs($gm)->get(route('gm.moderation.index', [
            'status' => 'all',
            'q' => 'Nordklinge',
        ]));

        $searchResponse->assertOk();
        $searchResponse->assertSee('PENDING-RABE');
        $searchResponse->assertSee('REJECTED-NACHT');
        $searchResponse->assertDontSee('APPROVED-WOLF');
    }

    public function test_gm_can_moderate_post_from_queue(): void
    {
        $gm = User::factory()->gm()->create();
        $author = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'content' => 'MODERATE-ME',
            'content_format' => 'plain',
            'post_type' => 'ic',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $response = $this->actingAs($gm)
            ->from(route('gm.moderation.index', ['status' => 'pending']))
            ->patch(route('posts.moderate', ['world' => $post->scene->campaign->world, 'post' => $post]), [
                'moderation_status' => 'approved',
                'moderation_note' => 'Kanonisch und regelkonform.',
            ]);

        $response->assertRedirect(route('gm.moderation.index', ['status' => 'pending']));

        $post->refresh();

        $this->assertSame('approved', $post->moderation_status);
        $this->assertSame($gm->id, $post->approved_by);
        $this->assertNotNull($post->approved_at);
        $this->assertDatabaseHas('post_moderation_logs', [
            'post_id' => $post->id,
            'moderator_id' => $gm->id,
            'previous_status' => 'pending',
            'new_status' => 'approved',
            'reason' => 'Kanonisch und regelkonform.',
        ]);
    }

    public function test_gm_can_bulk_moderate_filtered_posts(): void
    {
        $gm = User::factory()->gm()->create();
        $author = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $firstPending = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'content' => 'BULK-ZIEL-EINS',
            'content_format' => 'plain',
            'post_type' => 'ooc',
            'moderation_status' => 'pending',
        ]);
        $secondPending = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'content' => 'BULK-ZIEL-ZWEI',
            'content_format' => 'plain',
            'post_type' => 'ooc',
            'moderation_status' => 'pending',
        ]);
        $alreadyApproved = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'content' => 'NICHT-BULK',
            'content_format' => 'plain',
            'post_type' => 'ooc',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $gm->id,
        ]);

        $response = $this->actingAs($gm)
            ->from(route('gm.moderation.index', ['status' => 'pending', 'q' => 'BULK-ZIEL']))
            ->patch(route('gm.moderation.bulk-update'), [
                'status' => 'pending',
                'q' => 'BULK-ZIEL',
                'moderation_status' => 'approved',
                'moderation_note' => 'Bulk-Freigabe nach Sammelpruefung.',
            ]);

        $response->assertRedirectContains('/gm/moderation');
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('status=pending', $location);
        $this->assertStringContainsString('q=BULK-ZIEL', $location);

        $this->assertDatabaseHas('posts', [
            'id' => $firstPending->id,
            'moderation_status' => 'approved',
            'approved_by' => $gm->id,
        ]);
        $this->assertDatabaseHas('posts', [
            'id' => $secondPending->id,
            'moderation_status' => 'approved',
            'approved_by' => $gm->id,
        ]);
        $this->assertDatabaseHas('posts', [
            'id' => $alreadyApproved->id,
            'moderation_status' => 'approved',
            'approved_by' => $gm->id,
        ]);

        $this->assertDatabaseHas('post_moderation_logs', [
            'post_id' => $firstPending->id,
            'new_status' => 'approved',
            'reason' => 'Bulk-Freigabe nach Sammelpruefung.',
        ]);
        $this->assertDatabaseHas('post_moderation_logs', [
            'post_id' => $secondPending->id,
            'new_status' => 'approved',
            'reason' => 'Bulk-Freigabe nach Sammelpruefung.',
        ]);
    }

    public function test_gm_probe_endpoint_uses_d100_probe_roller_formula(): void
    {
        $gm = User::factory()->gm()->create();
        $author = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'content' => 'PROBE-ME',
            'content_format' => 'plain',
            'post_type' => 'ic',
            'moderation_status' => 'pending',
        ]);

        $probeRoller = $this->createMock(ProbeRoller::class);
        $probeRoller->expects($this->once())
            ->method('roll')
            ->with(DiceRoll::MODE_NORMAL, 5)
            ->willReturn([
                'mode' => DiceRoll::MODE_NORMAL,
                'rolls' => [50],
                'kept_roll' => 50,
                'modifier' => 5,
                'total' => 55,
                'critical_success' => false,
                'critical_failure' => false,
            ]);
        $this->app->instance(ProbeRoller::class, $probeRoller);

        $response = $this->actingAs($gm)->post(route('gm.moderation.probe', [
            'world' => $campaign->world,
            'post' => $post,
        ]), [
            'modifier' => 5,
            'target' => 60,
        ]);

        $response->assertOk();
        $response->assertSeeText('W100 50');
        $response->assertSeeText('+5');
        $response->assertSeeText('= 55 gegen 60');
        $response->assertSeeText('Erfolg');
        $response->assertDontSeeText('W20');
    }
}
