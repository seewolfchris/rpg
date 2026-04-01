<?php

declare(strict_types=1);

namespace App\Actions\SceneSubscription;

use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;

final class UnsubscribeSceneSubscriptionAction
{
    public function execute(User $user, Scene $scene): UnsubscribeSceneSubscriptionResult
    {
        $deleted = SceneSubscription::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $user->id)
            ->delete();

        return new UnsubscribeSceneSubscriptionResult(
            deleted: $deleted,
            statusMessage: 'Szenen-Abo entfernt.',
        );
    }
}
