<?php

namespace App\Domain\Campaign;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\User;
use App\Models\World;
use Illuminate\Support\Collection;

class CampaignParticipantResolver
{
    public function __construct(
        private readonly CampaignAccess $campaignAccess,
    ) {}

    /**
     * @return Collection<int, int<1, max>>
     */
    public function participantUserIds(Campaign $campaign): Collection
    {
        return $this->campaignAccess->participantUserIds($campaign);
    }

    public function canModerateCampaign(?User $user, Campaign $campaign): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return $campaign->canModeratePosts($user);
    }

    public function canModerateWorldQueue(User $user, World $world): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->moderatableCampaignIdsForWorld($user, $world)->isNotEmpty();
    }

    /**
     * @param  Collection<int, int<1, max>>|null  $participantUserIds
     */
    public function isParticipantUserId(Campaign $campaign, int $userId, ?Collection $participantUserIds = null): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $resolvedParticipantUserIds = $participantUserIds ?? $this->participantUserIds($campaign);

        return $resolvedParticipantUserIds->contains($userId);
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
            ->where('world_id', $campaign->world_id)
            ->with('user:id,name')
            ->orderBy('name')
            ->get(['id', 'user_id', 'name']);
    }

    /**
     * @return Collection<int, int<1, max>>
     */
    public function coGmCampaignIdsForWorld(User $user, World $world): Collection
    {
        return $this->moderatableCampaignIdsForWorld($user, $world);
    }

    /**
     * @return Collection<int, int<1, max>>
     */
    public function moderatableCampaignIdsForWorld(User $user, World $world): Collection
    {
        return $this->campaignAccess->moderatableCampaignIdsForWorld($user, $world);
    }

    public function hasCoGmAccessInWorld(User $user, World $world): bool
    {
        return $this->campaignAccess->hasCoGmAccessInWorld($user, $world);
    }
}
