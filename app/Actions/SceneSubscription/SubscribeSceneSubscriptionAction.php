<?php

declare(strict_types=1);

namespace App\Actions\SceneSubscription;

use App\Domain\Scene\SceneSubscriptionMutationService;
use App\Models\Scene;
use App\Models\User;

final class SubscribeSceneSubscriptionAction
{
    public function __construct(
        private readonly SceneSubscriptionMutationService $mutationService,
    ) {}

    public function execute(User $user, Scene $scene): SubscribeSceneSubscriptionResult
    {
        $subscription = $this->mutationService->subscribe($user, $scene);

        return new SubscribeSceneSubscriptionResult(
            subscription: $subscription,
            statusMessage: 'Szene abonniert.',
        );
    }
}
