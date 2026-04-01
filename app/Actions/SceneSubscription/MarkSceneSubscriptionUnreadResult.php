<?php

declare(strict_types=1);

namespace App\Actions\SceneSubscription;

use App\Contracts\Actions\StatusMessageResult;
use App\Models\SceneSubscription;

final readonly class MarkSceneSubscriptionUnreadResult implements StatusMessageResult
{
    public function __construct(
        public ?SceneSubscription $subscription,
        public string $statusMessage,
    ) {}

    public function statusMessage(): string
    {
        return $this->statusMessage;
    }
}
