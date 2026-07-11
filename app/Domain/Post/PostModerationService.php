<?php

declare(strict_types=1);

namespace App\Domain\Post;

use App\Models\Post;
use App\Models\PostModerationLog;
use App\Models\User;
use App\Support\Gamification\PointService;
use App\Support\Observability\DomainEventLogger;
use Throwable;

class PostModerationService
{
    public function __construct(
        private readonly PostModerationNotificationDispatcher $postModerationNotificationDispatcher,
        private readonly PointService $pointService,
        private readonly DomainEventLogger $logger,
    ) {}

    public function synchronizePersistentState(
        Post $post,
        ?User $moderator,
        string $previousStatus,
        ?string $moderationNote = null,
    ): bool {
        $newStatus = (string) $post->moderation_status;
        $hasModerationChange = $previousStatus !== $newStatus || $moderationNote !== null;

        if ($hasModerationChange) {
            PostModerationLog::query()->create([
                'post_id' => $post->id,
                'moderator_id' => $moderator?->id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'reason' => $moderationNote,
                'created_at' => now(),
            ]);

        }

        $this->pointService->syncApprovedPost($post);

        return $hasModerationChange;
    }

    public function dispatchAfterCommitEffects(
        Post $post,
        ?User $moderator,
        string $previousStatus,
        ?string $moderationNote = null,
    ): void {
        $newStatus = (string) $post->moderation_status;

        if ($previousStatus === $newStatus && $moderationNote === null) {
            return;
        }

        if ($moderator && $post->user_id !== $moderator->id) {
            try {
                $this->postModerationNotificationDispatcher->dispatch(
                    post: $post,
                    moderator: $moderator,
                    previousStatus: $previousStatus,
                    newStatus: $newStatus,
                    moderationNote: $moderationNote,
                );
            } catch (Throwable $throwable) {
                $this->logSafely('moderation.post_notification_dispatch_failed', [
                    'moderator_id' => $moderator->id,
                    ...$this->buildPostModerationLogContext($post, $previousStatus, $newStatus),
                    'error' => $throwable->getMessage(),
                    'outcome' => 'failed',
                ]);
            }
        }

        $this->logSafely('moderation.post_status_changed', [
            'world_slug' => (string) data_get($post, 'scene.campaign.world.slug', 'unknown'),
            'moderator_id' => $moderator?->id,
            ...$this->buildPostModerationLogContext($post, $previousStatus, $newStatus),
            'has_reason' => $moderationNote !== null,
            'outcome' => 'succeeded',
        ]);
    }

    /**
     * @return array{
     *   user_id: int,
     *   scene_id: int,
     *   post_id: int,
     *   previous_status: string,
     *   new_status: string
     * }
     */
    private function buildPostModerationLogContext(
        Post $post,
        string $previousStatus,
        string $newStatus,
    ): array {
        return [
            'user_id' => (int) $post->user_id,
            'scene_id' => (int) $post->scene_id,
            'post_id' => (int) $post->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logSafely(string $event, array $context): void
    {
        try {
            $this->logger->info($event, $context);
        } catch (Throwable) {
            // Observability must never turn an already committed moderation write into an HTTP failure.
        }
    }
}
