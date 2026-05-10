<?php

declare(strict_types=1);

namespace App\Actions\SceneSubscription;

use App\Domain\Scene\SceneSubscriptionMutationService;
use App\Models\Scene;
use App\Models\User;

final class UnsubscribeSceneSubscriptionAction
{
    public function __construct(
        private readonly SceneSubscriptionMutationService $mutationService,
    ) {}

    public function execute(User $user, Scene $scene): UnsubscribeSceneSubscriptionResult
    {
        $deleted = $this->mutationService->unsubscribe($user, $scene);

        return new UnsubscribeSceneSubscriptionResult(
            deleted: $deleted,
            statusMessage: 'Szenen-Abo entfernt.',
        );
    }
}
