<?php

declare(strict_types=1);

namespace App\Actions\SceneSubscription;

use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;

final class MarkSceneSubscriptionReadAction
{
    public function execute(User $user, Scene $scene): MarkSceneSubscriptionReadResult
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

            return new MarkSceneSubscriptionReadResult(
                subscription: $subscription,
                statusMessage: 'Szene als gelesen markiert.',
            );
        }

        $subscription->last_read_at = now()->toDateTimeString();
        $subscription->save();

        return new MarkSceneSubscriptionReadResult(
            subscription: $subscription,
            statusMessage: 'Szene enthält noch keine Beiträge.',
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
