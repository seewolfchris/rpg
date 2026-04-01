<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Post;

use App\Actions\Post\BulkModeratePostsAction;
use App\Actions\Post\BulkModeratePostsInput;
use App\Domain\Post\PostModerationService;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BulkModeratePostsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_bulk_moderates_selected_posts_within_co_gm_scope(): void
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();
        $author = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $coGm->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_CO_GM,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'content' => 'ACTION-BULK',
            'content_format' => 'plain',
            'post_type' => 'ic',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $result = app(BulkModeratePostsAction::class)->execute(new BulkModeratePostsInput(
            world: $campaign->world,
            moderator: $coGm,
            statusFilter: 'all',
            search: '',
            targetStatus: 'approved',
            moderationNote: 'Action-Freigabe',
            sceneId: 0,
            postIds: collect([$post->id]),
            isHtmxRequest: false,
        ));

        $this->assertSame(1, $result->affected);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'moderation_status' => 'approved',
            'approved_by' => $coGm->id,
        ]);
        $this->assertDatabaseHas('post_moderation_logs', [
            'post_id' => $post->id,
            'moderator_id' => $coGm->id,
            'previous_status' => 'pending',
            'new_status' => 'approved',
            'reason' => 'Action-Freigabe',
        ]);
    }

    public function test_it_throws_when_selected_post_is_outside_co_gm_scope(): void
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

        $foreignCampaign = Campaign::factory()->create([
            'owner_id' => $foreignOwner->id,
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
            'user_id' => $author->id,
            'content' => 'ACTION-AUSSERHALB',
            'content_format' => 'plain',
            'post_type' => 'ic',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $this->expectException(AuthorizationException::class);

        try {
            app(BulkModeratePostsAction::class)->execute(new BulkModeratePostsInput(
                world: $ownCampaign->world,
                moderator: $coGm,
                statusFilter: 'all',
                search: '',
                targetStatus: 'approved',
                moderationNote: 'Nicht erlaubt',
                sceneId: 0,
                postIds: collect([$foreignPost->id]),
                isHtmxRequest: false,
            ));
        } finally {
            $this->assertDatabaseHas('posts', [
                'id' => $foreignPost->id,
                'moderation_status' => 'pending',
                'approved_by' => null,
            ]);
        }
    }

    public function test_it_applies_status_and_search_filters_for_bulk_without_explicit_post_ids(): void
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

        $matchingPendingPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'content' => 'FILTER-MATCH',
            'content_format' => 'plain',
            'post_type' => 'ic',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);
        $nonMatchingPendingPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'content' => 'FILTER-OTHER',
            'content_format' => 'plain',
            'post_type' => 'ic',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);
        $matchingApprovedPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'content' => 'FILTER-MATCH',
            'content_format' => 'plain',
            'post_type' => 'ic',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $gm->id,
        ]);

        $result = app(BulkModeratePostsAction::class)->execute(new BulkModeratePostsInput(
            world: $campaign->world,
            moderator: $gm,
            statusFilter: 'pending',
            search: 'FILTER-MATCH',
            targetStatus: 'approved',
            moderationNote: 'Filter-Update',
            sceneId: 0,
            postIds: collect(),
            isHtmxRequest: false,
        ));

        $this->assertSame(1, $result->affected);
        $this->assertDatabaseHas('posts', [
            'id' => $matchingPendingPost->id,
            'moderation_status' => 'approved',
            'approved_by' => $gm->id,
        ]);
        $this->assertDatabaseHas('posts', [
            'id' => $nonMatchingPendingPost->id,
            'moderation_status' => 'pending',
        ]);
        $this->assertDatabaseHas('posts', [
            'id' => $matchingApprovedPost->id,
            'moderation_status' => 'approved',
            'approved_by' => $gm->id,
        ]);
    }

    public function test_it_rolls_back_all_bulk_changes_when_synchronization_fails_mid_batch(): void
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create();
        $author = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $coGm->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_CO_GM,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $firstPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'content' => 'BULK-ROLLBACK-EINS',
            'content_format' => 'plain',
            'post_type' => 'ic',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);
        $secondPost = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $author->id,
            'content' => 'BULK-ROLLBACK-ZWEI',
            'content_format' => 'plain',
            'post_type' => 'ic',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $invocationCount = 0;
        $failingModerationService = $this->createMock(PostModerationService::class);
        $failingModerationService->expects($this->exactly(2))
            ->method('synchronize')
            ->willReturnCallback(function () use (&$invocationCount): void {
                $invocationCount++;

                if ($invocationCount === 2) {
                    throw new RuntimeException('Forced synchronization failure');
                }
            });
        $this->app->instance(PostModerationService::class, $failingModerationService);

        try {
            app(BulkModeratePostsAction::class)->execute(new BulkModeratePostsInput(
                world: $campaign->world,
                moderator: $coGm,
                statusFilter: 'all',
                search: '',
                targetStatus: 'approved',
                moderationNote: 'Batch-Transaktion darf nicht partiell schreiben.',
                sceneId: 0,
                postIds: collect([$firstPost->id, $secondPost->id]),
                isHtmxRequest: false,
            ));

            $this->fail('Expected forced synchronization failure during bulk moderation.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced synchronization failure', $exception->getMessage());
        }

        $this->assertDatabaseHas('posts', [
            'id' => $firstPost->id,
            'moderation_status' => 'pending',
            'approved_by' => null,
        ]);
        $this->assertDatabaseHas('posts', [
            'id' => $secondPost->id,
            'moderation_status' => 'pending',
            'approved_by' => null,
        ]);

        $this->assertDatabaseCount('post_moderation_logs', 0);
    }
}
