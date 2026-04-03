<?php

namespace App\Jobs\Post;

use App\Domain\Post\PostMentionNotificationService;
use App\Models\Post;
use App\Models\User;
use App\Support\Observability\DomainEventLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RetryPostMentionNotificationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    public int $timeout = 30;

    public function __construct(
        private readonly int $postId,
        private readonly int $authorId,
        private readonly string $source = 'unknown',
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

    public function handle(
        PostMentionNotificationService $postMentionNotificationService,
        DomainEventLogger $logger,
    ): void {
        $post = Post::query()
            ->with('scene.campaign')
            ->find($this->postId);
        $author = User::query()->find($this->authorId);

        if (! $post instanceof Post || ! $author instanceof User) {
            $logger->info('post.mention_notifications_retry_skipped', [
                'post_id' => $this->postId,
                'author_id' => $this->authorId,
                'source' => $this->source,
                'attempt' => $this->attempts(),
                'reason' => 'post_or_author_missing',
                'outcome' => 'skipped',
            ]);

            return;
        }

        try {
            $recipientCount = $postMentionNotificationService->notifyMentions($post, $author);

            $logger->info('post.mention_notifications_retry_succeeded', [
                'post_id' => $post->id,
                'author_id' => $author->id,
                'scene_id' => $post->scene_id,
                'source' => $this->source,
                'attempt' => $this->attempts(),
                'recipient_count' => $recipientCount,
                'outcome' => 'succeeded',
            ]);
        } catch (Throwable $throwable) {
            $logger->info('post.mention_notifications_retry_failed', [
                'post_id' => $post->id,
                'author_id' => $author->id,
                'scene_id' => $post->scene_id,
                'source' => $this->source,
                'attempt' => $this->attempts(),
                'error' => $throwable->getMessage(),
                'outcome' => 'failed',
            ]);

            throw $throwable;
        }
    }

    public function failed(Throwable $throwable): void
    {
        app(DomainEventLogger::class)->info('post.mention_notifications_retry_exhausted', [
            'post_id' => $this->postId,
            'author_id' => $this->authorId,
            'source' => $this->source,
            'attempt' => $this->attempts(),
            'error' => $throwable->getMessage(),
            'outcome' => 'failed',
        ]);
    }
}
