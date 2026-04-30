<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\PlayerNote;
use App\Models\User;

class PlayerNotePolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $campaign->isVisibleTo($user);
    }

    public function view(User $user, PlayerNote $playerNote): bool
    {
        $campaign = $this->resolveCampaign($playerNote);

        if (! $campaign instanceof Campaign) {
            return false;
        }

        return $campaign->isVisibleTo($user) && $playerNote->belongsToUser($user);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $campaign->isVisibleTo($user);
    }

    public function update(User $user, PlayerNote $playerNote): bool
    {
        $campaign = $this->resolveCampaign($playerNote);

        return $campaign instanceof Campaign
            && $campaign->isVisibleTo($user)
            && $playerNote->belongsToUser($user);
    }

    public function delete(User $user, PlayerNote $playerNote): bool
    {
        $campaign = $this->resolveCampaign($playerNote);

        return $campaign instanceof Campaign
            && $campaign->isVisibleTo($user)
            && $playerNote->belongsToUser($user);
    }

    private function resolveCampaign(PlayerNote $playerNote): ?Campaign
    {
        if ($playerNote->relationLoaded('campaign')) {
            $campaign = $playerNote->getRelation('campaign');

            return $campaign instanceof Campaign ? $campaign : null;
        }

        /** @var Campaign|null $campaign */
        $campaign = Campaign::query()->find((int) $playerNote->campaign_id);

        if ($campaign instanceof Campaign) {
            $playerNote->setRelation('campaign', $campaign);
        }

        return $campaign;
    }
}
