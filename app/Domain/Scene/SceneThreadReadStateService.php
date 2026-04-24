<?php

declare(strict_types=1);

namespace App\Domain\Scene;

use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Models\World;

final class SceneThreadReadStateService
{
    public function __construct(
        private readonly SceneReadTrackingService $sceneReadTrackingService,
    ) {}

    public function resolveForShowAndMarkRead(Scene $scene, User $user): SceneThreadReadState
    {
        $subscription = $this->sceneSubscription($scene, $user);
        $lastReadPostIdBeforeOpen = $subscription instanceof SceneSubscription
            ? (int) ($subscription->last_read_post_id ?? 0)
            : 0;

        $readTracking = $this->sceneReadTrackingService->synchronize(
            scene: $scene,
            subscription: $subscription,
            lastReadPostIdBeforeOpen: $lastReadPostIdBeforeOpen,
        );

        return new SceneThreadReadState(
            subscription: $subscription,
            latestPostId: $readTracking->latestPostId,
            unreadPostsCount: 0,
            newPostsSinceLastRead: $readTracking->newPostsSinceLastRead,
            hasUnreadPosts: $readTracking->hasUnreadPosts,
            firstUnreadPostId: $readTracking->firstUnreadPostId,
            lastReadPostIdBeforeOpen: $readTracking->lastReadPostIdBeforeOpen,
        );
    }

    public function resolveForThreadRender(Scene $scene, User $user): SceneThreadReadState
    {
        $subscription = $this->sceneSubscription($scene, $user);
        $latestPostId = $this->latestScenePostId($scene);
        $lastReadPostIdBeforeOpen = $subscription instanceof SceneSubscription
            ? (int) ($subscription->last_read_post_id ?? 0)
            : 0;

        if (! $subscription || $latestPostId <= 0 || ! $subscription->hasUnread($latestPostId)) {
            return new SceneThreadReadState(
                subscription: $subscription,
                latestPostId: $latestPostId,
                unreadPostsCount: 0,
                newPostsSinceLastRead: 0,
                hasUnreadPosts: false,
                firstUnreadPostId: 0,
                lastReadPostIdBeforeOpen: $lastReadPostIdBeforeOpen,
            );
        }

        $unreadPostsCount = (int) Post::query()
            ->withTrashed()
            ->where('scene_id', $scene->id)
            ->where('id', '>', $lastReadPostIdBeforeOpen)
            ->count();
        $firstUnreadPostId = (int) Post::query()
            ->withTrashed()
            ->where('scene_id', $scene->id)
            ->where('id', '>', $lastReadPostIdBeforeOpen)
            ->orderBy('id')
            ->value('id');

        return new SceneThreadReadState(
            subscription: $subscription,
            latestPostId: $latestPostId,
            unreadPostsCount: $unreadPostsCount,
            newPostsSinceLastRead: 0,
            hasUnreadPosts: $unreadPostsCount > 0,
            firstUnreadPostId: $firstUnreadPostId,
            lastReadPostIdBeforeOpen: $lastReadPostIdBeforeOpen,
        );
    }

    public function unreadSceneCountForWorld(User $user, World $world): int
    {
        return (int) SceneSubscription::query()
            ->join('scenes', 'scenes.id', '=', 'scene_subscriptions.scene_id')
            ->join('campaigns', 'campaigns.id', '=', 'scenes.campaign_id')
            ->where('scene_subscriptions.user_id', (int) $user->id)
            ->where('campaigns.world_id', (int) $world->id)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('posts')
                    ->whereColumn('posts.scene_id', 'scene_subscriptions.scene_id')
                    ->whereRaw('posts.id > COALESCE(scene_subscriptions.last_read_post_id, 0)');
            })
            ->count();
    }

    private function sceneSubscription(Scene $scene, User $user): ?SceneSubscription
    {
        return SceneSubscription::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $user->id)
            ->first();
    }

    private function latestScenePostId(Scene $scene): int
    {
        return (int) Post::query()
            ->withTrashed()
            ->where('scene_id', $scene->id)
            ->max('id');
    }
}
