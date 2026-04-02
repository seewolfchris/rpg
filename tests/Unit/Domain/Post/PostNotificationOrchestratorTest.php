<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Post;

use App\Domain\Post\PostMentionNotificationService;
use App\Domain\Post\PostNotificationOrchestrator;
use App\Domain\Post\ScenePostNotificationService;
use App\Domain\Shared\Outbox\OutboxCandidateRecorder;
use App\Jobs\Post\RetryPostMentionNotificationsJob;
use App\Jobs\Post\RetryScenePostNotificationsJob;
use App\Models\Post;
use App\Models\User;
use App\Support\Observability\StructuredLogger;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class PostNotificationOrchestratorTest extends TestCase
{
    public function test_scene_notification_failure_dispatches_retry_and_records_outbox_candidate(): void
    {
        Queue::fake();

        $post = new Post;
        $post->id = 77;
        $post->scene_id = 11;

        $author = new User;
        $author->id = 8;

        $sceneService = $this->createMock(ScenePostNotificationService::class);
        $sceneService->expects($this->once())
            ->method('notifySceneParticipants')
            ->with($post, $author)
            ->willThrowException(new RuntimeException('scene failed'));

        $mentionService = $this->createMock(PostMentionNotificationService::class);
        $logger = $this->createMock(StructuredLogger::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'post.scene_notifications_failed',
                $this->callback(static fn (array $context): bool => (int) ($context['post_id'] ?? 0) === 77),
            );

        $recorder = $this->createMock(OutboxCandidateRecorder::class);
        $recorder->expects($this->once())
            ->method('record')
            ->with(
                'post.notifications',
                'scene_notifications_failed',
                $this->callback(static fn (array $payload): bool => (int) ($payload['post_id'] ?? 0) === 77),
                $this->isInstanceOf(RuntimeException::class),
            );

        $orchestrator = new PostNotificationOrchestrator(
            $sceneService,
            $mentionService,
            $logger,
            $recorder,
        );

        $result = $orchestrator->notifySceneParticipantsWithRetry($post, $author, 'unit-test');

        $this->assertSame([
            'in_app_recipients' => 0,
            'webpush_recipients' => 0,
        ], $result);
        Queue::assertPushed(RetryScenePostNotificationsJob::class, 1);
    }

    public function test_mention_failure_dispatches_retry_and_records_outbox_candidate(): void
    {
        Queue::fake();

        $post = new Post;
        $post->id = 91;
        $post->scene_id = 13;

        $author = new User;
        $author->id = 6;

        $sceneService = $this->createMock(ScenePostNotificationService::class);
        $mentionService = $this->createMock(PostMentionNotificationService::class);
        $mentionService->expects($this->once())
            ->method('notifyMentions')
            ->with($post, $author)
            ->willThrowException(new RuntimeException('mention failed'));

        $logger = $this->createMock(StructuredLogger::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'post.mention_notifications_failed',
                $this->callback(static fn (array $context): bool => (int) ($context['post_id'] ?? 0) === 91),
            );

        $recorder = $this->createMock(OutboxCandidateRecorder::class);
        $recorder->expects($this->once())
            ->method('record')
            ->with(
                'post.notifications',
                'mention_notifications_failed',
                $this->callback(static fn (array $payload): bool => (int) ($payload['post_id'] ?? 0) === 91),
                $this->isInstanceOf(RuntimeException::class),
            );

        $orchestrator = new PostNotificationOrchestrator(
            $sceneService,
            $mentionService,
            $logger,
            $recorder,
        );

        $result = $orchestrator->notifyMentionsWithRetry($post, $author, 'unit-test');

        $this->assertSame(0, $result);
        Queue::assertPushed(RetryPostMentionNotificationsJob::class, 1);
    }
}
