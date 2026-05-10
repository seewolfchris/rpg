<?php

declare(strict_types=1);

namespace App\Domain\Scene;

use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;

class SceneSubscriptionMutationService
{
    public function subscribe(User $user, Scene $scene): SceneSubscription
    {
        $latestPostId = $this->latestScenePostId($scene);

        return SceneSubscription::query()->updateOrCreate([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
        ], [
            'is_muted' => false,
            'last_read_post_id' => $latestPostId > 0 ? $latestPostId : null,
            'last_read_at' => now(),
        ]);
    }

    public function unsubscribe(User $user, Scene $scene): int
    {
        return SceneSubscription::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * @return array{subscription: SceneSubscription, latestPostId: int}
     */
    public function markRead(User $user, Scene $scene): array
    {
        $latestPostId = $this->latestScenePostId($scene);

        $subscription = SceneSubscription::query()->firstOrCreate([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
        ], [
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);

        if ($latestPostId > 0) {
            $subscription->markRead($latestPostId);

            return [
                'subscription' => $subscription,
                'latestPostId' => $latestPostId,
            ];
        }

        $subscription->last_read_at = now()->toDateTimeString();
        $subscription->save();

        return [
            'subscription' => $subscription,
            'latestPostId' => 0,
        ];
    }

    public function markUnread(User $user, Scene $scene): ?SceneSubscription
    {
        $subscription = SceneSubscription::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $subscription instanceof SceneSubscription) {
            return null;
        }

        $subscription->markUnread();

        return $subscription;
    }

    public function toggleMute(User $user, Scene $scene): SceneSubscription
    {
        $latestPostId = $this->latestScenePostId($scene);

        $subscription = SceneSubscription::query()->firstOrCreate([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
        ], [
            'is_muted' => false,
            'last_read_post_id' => $latestPostId > 0 ? $latestPostId : null,
            'last_read_at' => now(),
        ]);

        $subscription->is_muted = ! $subscription->is_muted;
        $subscription->save();

        return $subscription;
    }

    private function latestScenePostId(Scene $scene): int
    {
        return (int) Post::query()
            ->withTrashed()
            ->where('scene_id', $scene->id)
            ->max('id');
    }
}
