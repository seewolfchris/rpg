<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Post;

use App\Actions\Post\ApplyPostModerationTransitionAction;
use App\Domain\Post\PostModerationService;
use App\Domain\Post\PostNotificationOrchestrator;
use App\Jobs\Post\SendPostModerationStatusNotificationJob;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class ApplyPostModerationTransitionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_runs_single_moderation_transition_through_real_service_chain(): void
    {
        Queue::fake();

        [$gm, $player, $post] = $this->seedModerationContext();

        app(ApplyPostModerationTransitionAction::class)->execute(
            post: $post,
            moderator: $gm,
            targetStatus: 'approved',
            moderationNote: '  Freigabe nach Korrektur  ',
        );

        $post->refresh();

        $this->assertSame('approved', (string) $post->moderation_status);
        $this->assertSame((int) $gm->id, (int) ($post->approved_by ?? 0));
        $this->assertNotNull($post->approved_at);
        $this->assertDatabaseHas('post_moderation_logs', [
            'post_id' => $post->id,
            'moderator_id' => $gm->id,
            'previous_status' => 'pending',
            'new_status' => 'approved',
            'reason' => 'Freigabe nach Korrektur',
        ]);
        $this->assertDatabaseHas('point_events', [
            'user_id' => $player->id,
            'source_type' => 'post',
            'source_id' => $post->id,
            'event_key' => 'approved',
            'points' => 10,
        ]);
        Queue::assertPushed(SendPostModerationStatusNotificationJob::class, 1);
    }

    public function test_it_skips_notification_job_for_self_moderation(): void
    {
        Queue::fake();

        $gm = User::factory()->gm()->create();
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
            'user_id' => $gm->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Self moderation test',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        app(ApplyPostModerationTransitionAction::class)->execute(
            post: $post,
            moderator: $gm,
            targetStatus: 'approved',
            moderationNote: null,
        );

        Queue::assertNotPushed(SendPostModerationStatusNotificationJob::class);
        $this->assertDatabaseHas('post_moderation_logs', [
            'post_id' => $post->id,
            'moderator_id' => $gm->id,
            'previous_status' => 'pending',
            'new_status' => 'approved',
            'reason' => null,
        ]);
    }

    public function test_it_rolls_back_status_when_moderation_synchronization_fails(): void
    {
        [$gm, , $post] = $this->seedModerationContext();

        $moderationService = $this->createMock(PostModerationService::class);
        $moderationService->expects($this->once())
            ->method('synchronizePersistentState')
            ->willThrowException(new RuntimeException('forced synchronization failure'));
        $moderationService->expects($this->never())->method('dispatchAfterCommitEffects');
        $notificationOrchestrator = $this->createMock(PostNotificationOrchestrator::class);
        $notificationOrchestrator->expects($this->never())->method('notifySceneParticipantsWithRetry');
        $notificationOrchestrator->expects($this->never())->method('notifyMentionsWithRetry');

        $action = new ApplyPostModerationTransitionAction(
            $moderationService,
            app(DatabaseManager::class),
            $notificationOrchestrator,
        );

        try {
            $action->execute(
                post: $post,
                moderator: $gm,
                targetStatus: 'approved',
                moderationNote: null,
            );

            $this->fail('Expected moderation synchronization failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced synchronization failure', $exception->getMessage());
        }

        $post->refresh();

        $this->assertSame('pending', (string) $post->moderation_status);
        $this->assertNull($post->approved_at);
        $this->assertNull($post->approved_by);
    }

    /**
     * @return array{0: User, 1: User, 2: Post}
     */
    private function seedModerationContext(): array
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

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
            'user_id' => $player->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Needs moderation',
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        return [$gm, $player, $post];
    }
}
