<?php

namespace App\Jobs\Post;

use App\Domain\Post\ScenePostNotificationService;
use App\Models\Post;
use App\Models\User;
use App\Support\Observability\StructuredLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RetryScenePostNotificationsJob implements ShouldQueue
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
        ScenePostNotificationService $scenePostNotificationService,
        StructuredLogger $logger,
    ): void {
        $post = Post::query()
            ->with('scene.campaign')
            ->find($this->postId);
        $author = User::query()->find($this->authorId);

        if (! $post instanceof Post || ! $author instanceof User) {
            $logger->info('post.scene_notifications_retry_skipped', [
                'post_id' => $this->postId,
                'author_id' => $this->authorId,
                'source' => $this->source,
                'attempt' => $this->attempts(),
                'reason' => 'post_or_author_missing',
            ]);

            return;
        }

        try {
            $result = $scenePostNotificationService->notifySceneParticipants($post, $author);

            $logger->info('post.scene_notifications_retry_succeeded', [
                'post_id' => $post->id,
                'author_id' => $author->id,
                'scene_id' => $post->scene_id,
                'source' => $this->source,
                'attempt' => $this->attempts(),
                'in_app_recipients' => (int) ($result['in_app_recipients'] ?? 0),
                'webpush_recipients' => (int) ($result['webpush_recipients'] ?? 0),
            ]);
        } catch (Throwable $throwable) {
            $logger->info('post.scene_notifications_retry_failed', [
                'post_id' => $post->id,
                'author_id' => $author->id,
                'scene_id' => $post->scene_id,
                'source' => $this->source,
                'attempt' => $this->attempts(),
                'error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }

    public function failed(Throwable $throwable): void
    {
        app(StructuredLogger::class)->info('post.scene_notifications_retry_exhausted', [
            'post_id' => $this->postId,
            'author_id' => $this->authorId,
            'source' => $this->source,
            'attempt' => $this->attempts(),
            'error' => $throwable->getMessage(),
        ]);
    }
}
