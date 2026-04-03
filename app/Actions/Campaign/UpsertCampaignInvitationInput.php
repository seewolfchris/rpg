<?php

declare(strict_types=1);

namespace App\Actions\Campaign;

use App\Models\Campaign;

final class UpsertCampaignInvitationInput
{
    public function __construct(
        public readonly Campaign $campaign,
        public readonly int $inviteeUserId,
        public readonly int $inviterUserId,
        public readonly string $requestedRole,
    ) {}
}

