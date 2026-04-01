<?php

declare(strict_types=1);

namespace App\Actions\SceneSubscription;

use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;

final class MarkSceneSubscriptionUnreadAction
{
    public function execute(User $user, Scene $scene): MarkSceneSubscriptionUnreadResult
    {
        $subscription = SceneSubscription::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $subscription instanceof SceneSubscription) {
            return new MarkSceneSubscriptionUnreadResult(
                subscription: null,
                statusMessage: 'Szene ist nicht abonniert.',
            );
        }

        $subscription->markUnread();

        return new MarkSceneSubscriptionUnreadResult(
            subscription: $subscription,
            statusMessage: 'Szene als ungelesen markiert.',
        );
    }
}
