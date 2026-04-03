<?php

declare(strict_types=1);

namespace App\Jobs\Post;

use App\Models\Post;
use App\Models\User;
use App\Notifications\PostModerationStatusNotification;
use App\Support\Observability\DomainEventLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendPostModerationStatusNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    public int $timeout = 30;

    public function __construct(
        private readonly int $postId,
        private readonly int $moderatorId,
        private readonly string $previousStatus,
        private readonly string $newStatus,
        private readonly ?string $moderationNote,
    ) {
        $this->afterCommit = true;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(DomainEventLogger $logger): void
    {
        $post = Post::query()
            ->with(['scene.campaign', 'user'])
            ->find($this->postId);
        $moderator = User::query()->find($this->moderatorId);

        if (! $post instanceof Post || ! $moderator instanceof User) {
            $logger->info('moderation.post_notification_skipped', [
                'post_id' => $this->postId,
                'moderator_id' => $this->moderatorId,
                'attempt' => $this->attempts(),
                'reason' => 'post_or_moderator_missing',
                'outcome' => 'skipped',
            ]);

            return;
        }

        if ($post->user_id === $moderator->id) {
            $logger->info('moderation.post_notification_skipped', [
                'post_id' => $post->id,
                'moderator_id' => $moderator->id,
                'attempt' => $this->attempts(),
                'reason' => 'author_is_moderator',
                'outcome' => 'skipped',
            ]);

            return;
        }

        $recipient = $post->user;
        if (! $recipient instanceof User) {
            $logger->info('moderation.post_notification_skipped', [
                'post_id' => $post->id,
                'moderator_id' => $moderator->id,
                'attempt' => $this->attempts(),
                'reason' => 'recipient_missing',
                'outcome' => 'skipped',
            ]);

            return;
        }

        try {
            $recipient->notify(new PostModerationStatusNotification(
                post: $post,
                moderator: $moderator,
                previousStatus: $this->previousStatus,
                newStatus: $this->newStatus,
                moderationNote: $this->moderationNote,
            ));

            $logger->info('moderation.post_notification_sent', [
                'post_id' => $post->id,
                'moderator_id' => $moderator->id,
                'recipient_id' => $post->user_id,
                'scene_id' => $post->scene_id,
                'attempt' => $this->attempts(),
                'previous_status' => $this->previousStatus,
                'new_status' => $this->newStatus,
                'has_reason' => $this->moderationNote !== null,
                'outcome' => 'succeeded',
            ]);
        } catch (Throwable $throwable) {
            $logger->info('moderation.post_notification_failed', [
                'post_id' => $post->id,
                'moderator_id' => $moderator->id,
                'recipient_id' => $post->user_id,
                'scene_id' => $post->scene_id,
                'attempt' => $this->attempts(),
                'previous_status' => $this->previousStatus,
                'new_status' => $this->newStatus,
                'error' => $throwable->getMessage(),
                'outcome' => 'failed',
            ]);

            throw $throwable;
        }
    }

    public function failed(Throwable $throwable): void
    {
        app(DomainEventLogger::class)->info('moderation.post_notification_exhausted', [
            'post_id' => $this->postId,
            'moderator_id' => $this->moderatorId,
            'attempt' => $this->attempts(),
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'error' => $throwable->getMessage(),
            'outcome' => 'failed',
        ]);
    }
}
