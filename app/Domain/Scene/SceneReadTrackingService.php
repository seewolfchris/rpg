<?php

namespace App\Domain\Scene;

use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;

class SceneReadTrackingService
{
    public function synchronize(
        Scene $scene,
        ?SceneSubscription $subscription,
        int $lastReadPostIdBeforeOpen,
    ): SceneReadTrackingResult {
        $latestPostId = (int) Post::query()
            ->where('scene_id', $scene->id)
            ->max('id');

        $newPostsSinceLastRead = 0;
        $hasUnreadPosts = false;
        $firstUnreadPostId = 0;

        if ($subscription) {
            $hasUnreadPosts = $subscription->hasUnread($latestPostId);

            if ($hasUnreadPosts) {
                $newPostsSinceLastRead = $lastReadPostIdBeforeOpen > 0
                    ? Post::query()
                        ->where('scene_id', $scene->id)
                        ->where('id', '>', $lastReadPostIdBeforeOpen)
                        ->count()
                    : Post::query()
                        ->where('scene_id', $scene->id)
                        ->count();

                $firstUnreadPostId = (int) Post::query()
                    ->where('scene_id', $scene->id)
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
}
