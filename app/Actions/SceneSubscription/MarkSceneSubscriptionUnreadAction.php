<?php

declare(strict_types=1);

namespace App\Actions\SceneSubscription;

use App\Domain\Scene\SceneSubscriptionMutationService;
use App\Models\Scene;
use App\Models\User;

final class MarkSceneSubscriptionUnreadAction
{
    public function __construct(
        private readonly SceneSubscriptionMutationService $mutationService,
    ) {}

    public function execute(User $user, Scene $scene): MarkSceneSubscriptionUnreadResult
    {
        $subscription = $this->mutationService->markUnread($user, $scene);

        if ($subscription === null) {
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
