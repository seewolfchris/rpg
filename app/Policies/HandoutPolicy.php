<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\Handout;
use App\Models\User;

class HandoutPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $campaign->isVisibleTo($user);
    }

    public function view(User $user, Handout $handout): bool
    {
        $campaign = $this->resolveCampaign($handout);

        if (! $campaign instanceof Campaign) {
            return false;
        }

        if (! $campaign->isVisibleTo($user)) {
            return false;
        }

        if ($campaign->canManageCampaign($user)) {
            return true;
        }

        return $handout->revealed_at !== null;
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $campaign->canManageCampaign($user);
    }

    public function update(User $user, Handout $handout): bool
    {
        $campaign = $this->resolveCampaign($handout);

        return $campaign instanceof Campaign
            && $campaign->canManageCampaign($user);
    }

    public function delete(User $user, Handout $handout): bool
    {
        $campaign = $this->resolveCampaign($handout);

        return $campaign instanceof Campaign
            && $campaign->canManageCampaign($user);
    }

    public function reveal(User $user, Handout $handout): bool
    {
        $campaign = $this->resolveCampaign($handout);

        return $campaign instanceof Campaign
            && $campaign->canManageCampaign($user);
    }

    public function unreveal(User $user, Handout $handout): bool
    {
        $campaign = $this->resolveCampaign($handout);

        return $campaign instanceof Campaign
            && $campaign->canManageCampaign($user);
    }

    private function resolveCampaign(Handout $handout): ?Campaign
    {
        if ($handout->relationLoaded('campaign')) {
            $campaign = $handout->getRelation('campaign');

            return $campaign instanceof Campaign ? $campaign : null;
        }

        /** @var Campaign|null $campaign */
        $campaign = Campaign::query()->find((int) $handout->campaign_id);

        if ($campaign instanceof Campaign) {
            $handout->setRelation('campaign', $campaign);
        }

        return $campaign;
    }
}
