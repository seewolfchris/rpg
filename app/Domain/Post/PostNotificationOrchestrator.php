<?php

namespace App\Domain\Post;

use App\Domain\Shared\Outbox\OutboxCandidateRecorder;
use App\Jobs\Post\RetryPostMentionNotificationsJob;
use App\Jobs\Post\RetryScenePostNotificationsJob;
use App\Models\Post;
use App\Models\User;
use App\Support\Observability\DomainEventLogger;
use Throwable;

class PostNotificationOrchestrator
{
    public function __construct(
        private readonly ScenePostNotificationService $scenePostNotificationService,
        private readonly PostMentionNotificationService $postMentionNotificationService,
        private readonly DomainEventLogger $logger,
        private readonly OutboxCandidateRecorder $outboxCandidateRecorder,
    ) {}

    /**
     * @return array{in_app_recipients: int, webpush_recipients: int}
     */
    public function notifySceneParticipantsWithRetry(Post $post, User $author, string $source): array
    {
        try {
            return $this->scenePostNotificationService->notifySceneParticipants($post, $author);
        } catch (Throwable $throwable) {
            $this->logger->info('post.scene_notifications_failed', [
                'user_id' => $author->id,
                'scene_id' => $post->scene_id,
                'post_id' => $post->id,
                'source' => $source,
                'error' => $throwable->getMessage(),
                'outcome' => 'failed',
            ]);
            $this->outboxCandidateRecorder->record(
                stream: 'post.notifications',
                eventKey: 'scene_notifications_failed',
                payload: [
                    'user_id' => (int) $author->id,
                    'scene_id' => (int) $post->scene_id,
                    'post_id' => (int) $post->id,
                    'source' => $source,
                    'retry_job' => RetryScenePostNotificationsJob::class,
                ],
                throwable: $throwable,
            );

            $this->dispatchSceneNotificationRetry($post, $author, $source, $throwable);

            return [
                'in_app_recipients' => 0,
                'webpush_recipients' => 0,
            ];
        }
    }

    public function notifyMentionsWithRetry(Post $post, User $author, string $source): int
    {
        try {
            return $this->postMentionNotificationService->notifyMentions($post, $author);
        } catch (Throwable $throwable) {
            $this->logger->info('post.mention_notifications_failed', [
                'user_id' => $author->id,
                'scene_id' => $post->scene_id,
                'post_id' => $post->id,
                'source' => $source,
                'error' => $throwable->getMessage(),
                'outcome' => 'failed',
            ]);
            $this->outboxCandidateRecorder->record(
                stream: 'post.notifications',
                eventKey: 'mention_notifications_failed',
                payload: [
                    'user_id' => (int) $author->id,
                    'scene_id' => (int) $post->scene_id,
                    'post_id' => (int) $post->id,
                    'source' => $source,
                    'retry_job' => RetryPostMentionNotificationsJob::class,
                ],
                throwable: $throwable,
            );

            $this->dispatchMentionRetry($post, $author, $source, $throwable);

            return 0;
        }
    }

    private function dispatchSceneNotificationRetry(Post $post, User $author, string $source, Throwable $throwable): void
    {
        try {
            RetryScenePostNotificationsJob::dispatch(
                postId: (int) $post->id,
                authorId: (int) $author->id,
                source: $source,
            );
        } catch (Throwable $dispatchThrowable) {
            $this->logger->info('post.scene_notifications_retry_dispatch_failed', [
                'user_id' => $author->id,
                'scene_id' => $post->scene_id,
                'post_id' => $post->id,
                'source' => $source,
                'error' => $throwable->getMessage(),
                'dispatch_error' => $dispatchThrowable->getMessage(),
                'outcome' => 'failed',
            ]);
            $this->outboxCandidateRecorder->record(
                stream: 'post.notifications',
                eventKey: 'scene_notification_retry_dispatch_failed',
                payload: [
                    'user_id' => (int) $author->id,
                    'scene_id' => (int) $post->scene_id,
                    'post_id' => (int) $post->id,
                    'source' => $source,
                    'retry_job' => RetryScenePostNotificationsJob::class,
                ],
                throwable: $dispatchThrowable,
            );
        }
    }

    private function dispatchMentionRetry(Post $post, User $author, string $source, Throwable $throwable): void
    {
        try {
            RetryPostMentionNotificationsJob::dispatch(
                postId: (int) $post->id,
                authorId: (int) $author->id,
                source: $source,
            );
        } catch (Throwable $dispatchThrowable) {
            $this->logger->info('post.mention_notifications_retry_dispatch_failed', [
                'user_id' => $author->id,
                'scene_id' => $post->scene_id,
                'post_id' => $post->id,
                'source' => $source,
                'error' => $throwable->getMessage(),
                'dispatch_error' => $dispatchThrowable->getMessage(),
                'outcome' => 'failed',
            ]);
            $this->outboxCandidateRecorder->record(
                stream: 'post.notifications',
                eventKey: 'mention_notification_retry_dispatch_failed',
                payload: [
                    'user_id' => (int) $author->id,
                    'scene_id' => (int) $post->scene_id,
                    'post_id' => (int) $post->id,
                    'source' => $source,
                    'retry_job' => RetryPostMentionNotificationsJob::class,
                ],
                throwable: $dispatchThrowable,
            );
        }
    }
}
