<?php

namespace App\Domain\Campaign;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Character;
use Illuminate\Support\Collection;

class CampaignParticipantResolver
{
    /**
     * @return Collection<int, int>
     */
    public function participantUserIds(Campaign $campaign): Collection
    {
        $participantUserIds = $campaign->invitations()
            ->where('status', CampaignInvitation::STATUS_ACCEPTED)
            ->pluck('user_id');

        return $participantUserIds
            ->merge([(int) $campaign->owner_id])
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, Character>
     */
    public function probeCharacters(Campaign $campaign): Collection
    {
        $participantUserIds = $this->participantUserIds($campaign);

        if ($participantUserIds->isEmpty()) {
            return collect();
        }

        return Character::query()
            ->whereIn('user_id', $participantUserIds)
            ->with('user:id,name')
            ->orderBy('name')
            ->get(['id', 'user_id', 'name']);
    }
}
