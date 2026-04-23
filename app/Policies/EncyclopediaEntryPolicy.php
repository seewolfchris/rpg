<?php

namespace App\Policies;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Models\EncyclopediaEntry;
use App\Models\User;
use App\Models\World;

class EncyclopediaEntryPolicy
{
    public function propose(User $user): bool
    {
        return true;
    }

    public function updateProposal(User $user, EncyclopediaEntry $entry): bool
    {
        if ((int) $entry->created_by !== (int) $user->id) {
            return false;
        }

        return in_array((string) $entry->status, [
            EncyclopediaEntry::STATUS_DRAFT,
            EncyclopediaEntry::STATUS_PENDING,
            EncyclopediaEntry::STATUS_REJECTED,
        ], true);
    }

    public function review(User $user, World $world): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->campaignParticipantResolver()->canModerateWorldQueue($user, $world);
    }

    private function campaignParticipantResolver(): CampaignParticipantResolver
    {
        /** @var CampaignParticipantResolver $resolver */
        $resolver = app(CampaignParticipantResolver::class);

        return $resolver;
    }
}
