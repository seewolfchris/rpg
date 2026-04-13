<?php

declare(strict_types=1);

namespace App\Actions\Campaign;

final class RespondToCampaignInvitationResult
{
    public function __construct(
        public readonly bool $alreadyClosed,
        public readonly bool $isAccepted,
    ) {}
}
