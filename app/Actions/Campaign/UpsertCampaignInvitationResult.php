<?php

declare(strict_types=1);

namespace App\Actions\Campaign;

use App\Models\CampaignInvitation;

final class UpsertCampaignInvitationResult
{
    public function __construct(
        public readonly CampaignInvitation $invitation,
        public readonly bool $isNew,
        public readonly bool $wasAccepted,
    ) {}
}

