<?php

declare(strict_types=1);

namespace App\Actions\SceneSubscription;

use App\Domain\Scene\SceneSubscriptionMutationService;
use App\Models\Scene;
use App\Models\User;

final class MarkSceneSubscriptionReadAction
{
    public function __construct(
        private readonly SceneSubscriptionMutationService $mutationService,
    ) {}

    public function execute(User $user, Scene $scene): MarkSceneSubscriptionReadResult
    {
        $mutationResult = $this->mutationService->markRead($user, $scene);

        return new MarkSceneSubscriptionReadResult(
            subscription: $mutationResult['subscription'],
            statusMessage: $mutationResult['latestPostId'] > 0
                ? 'Szene als gelesen markiert.'
                : 'Szene enthält noch keine Beiträge.',
        );
    }
}
