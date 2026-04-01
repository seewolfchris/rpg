<?php

declare(strict_types=1);

namespace App\Actions\SceneSubscription;

use App\Contracts\Actions\StatusMessageResult;

final readonly class BulkUpdateSceneSubscriptionsResult implements StatusMessageResult
{
    public function __construct(
        public int $affected,
        public string $message,
    ) {}

    public function statusMessage(): string
    {
        return $this->message.' Betroffene Abos: '.$this->affected.'.';
    }

    public function flashMessage(): string
    {
        return $this->statusMessage();
    }
}
