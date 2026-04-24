<?php

declare(strict_types=1);

namespace App\Actions\SceneSubscription;

use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;

final class ToggleSceneSubscriptionMuteAction
{
    public function execute(User $user, Scene $scene): ToggleSceneSubscriptionMuteResult
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

        return new ToggleSceneSubscriptionMuteResult(
            subscription: $subscription,
            statusMessage: $subscription->is_muted
                ? 'Szenen-Benachrichtigungen stummgeschaltet.'
                : 'Szenen-Benachrichtigungen aktiviert.',
        );
    }

    private function latestScenePostId(Scene $scene): int
    {
        return (int) Post::query()
            ->withTrashed()
            ->where('scene_id', $scene->id)
            ->max('id');
    }
}
