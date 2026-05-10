<?php

declare(strict_types=1);

namespace App\Actions\SceneSubscription;

use App\Domain\Scene\SceneSubscriptionMutationService;
use App\Models\Scene;
use App\Models\User;

final class ToggleSceneSubscriptionMuteAction
{
    public function __construct(
        private readonly SceneSubscriptionMutationService $mutationService,
    ) {}

    public function execute(User $user, Scene $scene): ToggleSceneSubscriptionMuteResult
    {
        $subscription = $this->mutationService->toggleMute($user, $scene);

        return new ToggleSceneSubscriptionMuteResult(
            subscription: $subscription,
            statusMessage: $subscription->is_muted
                ? 'Szenen-Benachrichtigungen stummgeschaltet.'
                : 'Szenen-Benachrichtigungen aktiviert.',
        );
    }
}
