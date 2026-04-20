<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Models\Post;
use App\Models\PostReaction;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;

final class CreatePostReactionAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(World $world, Post $post, User $reactor, string $emoji): void
    {
        try {
            $this->runCreateTransaction($world, $post, $reactor, $emoji);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateReactionKey($exception)) {
                throw $exception;
            }

            $this->runCreateTransaction($world, $post, $reactor, $emoji);
        }
    }

    private function runCreateTransaction(World $world, Post $post, User $reactor, string $emoji): void
    {
        $this->db->transaction(function () use ($world, $post, $reactor, $emoji): void {
            $lockedPost = $this->lockAndVerifyContext($world, $post);
            $lockedReaction = $this->lockExistingReaction($lockedPost, $reactor, $emoji);

            $this->persistReaction($lockedPost, $reactor, $emoji, $lockedReaction);
        }, 3);
    }

    private function lockAndVerifyContext(World $world, Post $post): Post
    {
        /** @var Post $lockedPost */
        $lockedPost = Post::query()
            ->whereKey((int) $post->id)
            ->where('scene_id', (int) $post->scene_id)
            ->whereHas('scene.campaign', static function (Builder $campaignQuery) use ($world): void {
                $campaignQuery->where('world_id', (int) $world->id);
            })
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedPost;
    }

    private function lockExistingReaction(Post $post, User $reactor, string $emoji): ?PostReaction
    {
        /** @var PostReaction|null $reaction */
        $reaction = PostReaction::query()
            ->where('post_id', (int) $post->id)
            ->where('user_id', (int) $reactor->id)
            ->where('emoji', $emoji)
            ->lockForUpdate()
            ->first();

        return $reaction;
    }

    private function persistReaction(Post $post, User $reactor, string $emoji, ?PostReaction $reaction): void
    {
        if ($reaction instanceof PostReaction) {
            $reaction->touch();

            return;
        }

        PostReaction::query()->create([
            'post_id' => (int) $post->id,
            'user_id' => (int) $reactor->id,
            'emoji' => $emoji,
        ]);
    }

    private function isDuplicateReactionKey(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        $driverCode = is_array($errorInfo) && isset($errorInfo[1])
            ? (int) $errorInfo[1]
            : 0;
        $message = strtolower($exception->getMessage());

        if ($driverCode === 1062) {
            return true;
        }

        if (str_contains($message, 'duplicate entry')) {
            return true;
        }

        return str_contains($message, 'unique constraint failed');
    }
}
