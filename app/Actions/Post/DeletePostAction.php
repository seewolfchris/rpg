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

    public function execute(Post $post): void
    {
        $this->db->transaction(function () use ($post): void {
            $lockedPost = $this->lockAndVerifyContext($post);

            $this->revokePointsAndDeletePost($lockedPost);
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

    private function revokePointsAndDeletePost(Post $post): void
    {
        $this->pointService->revokeApprovedPostPoints($post);
        $post->delete();
    }
}
