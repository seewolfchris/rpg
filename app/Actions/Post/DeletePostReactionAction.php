<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Models\Post;
use App\Models\PostReaction;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;

final class DeletePostReactionAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(World $world, Post $post, User $reactor, string $emoji): void
    {
        $this->db->transaction(function () use ($world, $post, $reactor, $emoji): void {
            $lockedPost = $this->lockAndVerifyContext($world, $post);
            $lockedReaction = $this->lockExistingReaction($lockedPost, $reactor, $emoji);

            $this->persistDeletion($lockedReaction);
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

    private function persistDeletion(?PostReaction $reaction): void
    {
        if ($reaction instanceof PostReaction) {
            $reaction->delete();
        }
    }
}
