<?php

declare(strict_types=1);

namespace App\Actions\SceneSubscription;

use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;

final class SubscribeSceneSubscriptionAction
{
    public function execute(User $user, Scene $scene): SubscribeSceneSubscriptionResult
    {
        $latestPostId = (int) Post::query()
            ->withTrashed()
            ->where('scene_id', $scene->id)
            ->max('id');

        $subscription = SceneSubscription::query()->updateOrCreate([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
        ], [
            'is_muted' => false,
            'last_read_post_id' => $latestPostId > 0 ? $latestPostId : null,
            'last_read_at' => now(),
        ]);

        return new SubscribeSceneSubscriptionResult(
            subscription: $subscription,
            statusMessage: 'Szene abonniert.',
        );
    }
}
