<?php

namespace App\Domain\Scene;

use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;

class SceneReadTrackingService
{
    public function __construct(
        private readonly ScenePostVisibility $scenePostVisibility,
    ) {}

    public function synchronize(
        Scene $scene,
        User $user,
        ?SceneSubscription $subscription,
        int $lastReadPostIdBeforeOpen,
    ): SceneReadTrackingResult {
        $latestPostId = (int) $this->visiblePostQuery($scene, $user)->max('id');

        $newPostsSinceLastRead = 0;
        $hasUnreadPosts = false;
        $firstUnreadPostId = 0;

        if ($subscription) {
            $hasUnreadPosts = $subscription->hasUnread($latestPostId);

            if ($hasUnreadPosts) {
                $newPostsSinceLastRead = $lastReadPostIdBeforeOpen > 0
                    ? $this->visiblePostQuery($scene, $user)
                        ->where('id', '>', $lastReadPostIdBeforeOpen)
                        ->count()
                    : $this->visiblePostQuery($scene, $user)
                        ->count();

                $firstUnreadPostId = (int) $this->visiblePostQuery($scene, $user)
                    ->when(
                        $lastReadPostIdBeforeOpen > 0,
                        fn ($query) => $query->where('id', '>', $lastReadPostIdBeforeOpen),
                    )
                    ->orderBy('id')
                    ->value('id');

                $subscription->markRead($latestPostId);
                $subscription->refresh();
                $hasUnreadPosts = false;
            }
        }

        return new SceneReadTrackingResult(
            latestPostId: $latestPostId,
            lastReadPostIdBeforeOpen: $lastReadPostIdBeforeOpen,
            newPostsSinceLastRead: $newPostsSinceLastRead,
            hasUnreadPosts: $hasUnreadPosts,
            firstUnreadPostId: $firstUnreadPostId,
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Post>
     */
    private function visiblePostQuery(Scene $scene, User $user): \Illuminate\Database\Eloquent\Builder
    {
        $query = Post::query()
            ->withTrashed()
            ->where('scene_id', (int) $scene->id);

        return $this->scenePostVisibility->apply($query, $scene, $user);
    }
}
