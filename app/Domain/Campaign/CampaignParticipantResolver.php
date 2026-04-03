<?php

namespace App\Domain\Campaign;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Character;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CampaignParticipantResolver
{
    /**
     * @return Collection<int, int<1, max>>
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

    public function canModerateCampaign(?User $user, Campaign $campaign): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isGmOrAdmin()) {
            return true;
        }

        return $this->hasCampaignRole($campaign, $user, CampaignInvitation::ROLE_CO_GM);
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
        return CampaignInvitation::query()
            ->where('user_id', (int) $user->id)
            ->where('status', CampaignInvitation::STATUS_ACCEPTED)
            ->where('role', CampaignInvitation::ROLE_CO_GM)
            ->whereHas('campaign', function (Builder $campaignQuery) use ($world): void {
                $campaignQuery->where('world_id', (int) $world->id);
            })
            ->pluck('campaign_id')
            ->map(static fn ($campaignId): int => (int) $campaignId)
            ->filter(static fn (int $campaignId): bool => $campaignId > 0)
            ->unique()
            ->values();
    }

    public function hasCoGmAccessInWorld(User $user, World $world): bool
    {
        return $this->coGmCampaignIdsForWorld($user, $world)->isNotEmpty();
    }

    private function hasCampaignRole(Campaign $campaign, User $user, string $role): bool
    {
        return $campaign->invitations()
            ->where('user_id', (int) $user->id)
            ->where('status', CampaignInvitation::STATUS_ACCEPTED)
            ->where('role', $role)
            ->exists();
    }
}
