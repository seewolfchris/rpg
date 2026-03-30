<?php

declare(strict_types=1);

namespace App\Actions\Scene;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BuildSceneThreadPageDataAction
{
    public function execute(Scene $scene, Campaign $campaign, User $user): SceneThreadPageData
    {
        $posts = $this->threadPostsPaginator($scene);
        $subscription = $this->sceneSubscription($scene, $user);
        $latestPostId = $this->latestScenePostId($scene);
        $unreadPostsCount = $this->sceneUnreadPostsCount($scene, $subscription, $latestPostId);
        $canModerateScene = $this->canModerateScene($user, $campaign);

        return new SceneThreadPageData(
            posts: $posts,
            subscription: $subscription,
            latestPostId: $latestPostId,
            unreadPostsCount: $unreadPostsCount,
            canModerateScene: $canModerateScene,
        );
    }

    private function sceneSubscription(Scene $scene, User $user): ?SceneSubscription
    {
        return SceneSubscription::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * @return LengthAwarePaginator<int, Post>
     */
    private function threadPostsPaginator(Scene $scene): LengthAwarePaginator
    {
        return Post::query()
            ->where('scene_id', $scene->id)
            ->with(Post::THREAD_PAGE_RELATIONS)
            ->latestByIdHotpath()
            ->paginate(Post::THREAD_POSTS_PER_PAGE)
            ->withQueryString();
    }

    private function latestScenePostId(Scene $scene): int
    {
        return (int) Post::query()
            ->where('scene_id', $scene->id)
            ->max('id');
    }

    private function sceneUnreadPostsCount(Scene $scene, ?SceneSubscription $subscription, int $latestPostId): int
    {
        if (! $subscription || $latestPostId <= 0 || ! $subscription->hasUnread($latestPostId)) {
            return 0;
        }

        return (int) Post::query()
            ->where('scene_id', $scene->id)
            ->where('id', '>', (int) ($subscription->last_read_post_id ?? 0))
            ->count();
    }

    private function canModerateScene(User $user, Campaign $campaign): bool
    {
        return $user->isGmOrAdmin() || $campaign->isCoGm($user);
    }
}
