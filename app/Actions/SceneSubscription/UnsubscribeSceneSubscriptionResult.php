<?php

declare(strict_types=1);

namespace App\Actions\SceneSubscription;

use App\Contracts\Actions\StatusMessageResult;

final readonly class UnsubscribeSceneSubscriptionResult implements StatusMessageResult
{
    public function __construct(
        public int $deleted,
        public string $statusMessage,
    ) {}

    public function statusMessage(): string
    {
        return $this->statusMessage;
    }
}
