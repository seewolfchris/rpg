<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Models\Post;
use App\Support\Gamification\PointService;
use Illuminate\Database\DatabaseManager;

final class DeletePostAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly PointService $pointService,
    ) {}

    public function execute(Post $post, ?int $deletedByUserId = null): void
    {
        $this->db->transaction(function () use ($post, $deletedByUserId): void {
            $lockedPost = $this->lockAndVerifyContext($post);

            $this->revokePointsAndDeletePost($lockedPost, $deletedByUserId);
        }, 3);
    }

    private function lockAndVerifyContext(Post $post): Post
    {
        /** @var Post $lockedPost */
        $lockedPost = Post::query()
            ->whereKey((int) $post->id)
            ->where('scene_id', (int) $post->scene_id)
            ->whereHas('scene.campaign.world')
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedPost;
    }

    private function revokePointsAndDeletePost(Post $post, ?int $deletedByUserId): void
    {
        $this->pointService->revokeApprovedPostPoints($post);
        $post->forceFill([
            'deleted_by' => $deletedByUserId !== null && $deletedByUserId > 0
                ? $deletedByUserId
                : null,
        ])->save();
        $post->delete();
    }
}
