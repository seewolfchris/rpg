<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\StoryLogEntry;
use App\Models\User;

class StoryLogEntryPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $campaign->isVisibleTo($user);
    }

    public function view(User $user, StoryLogEntry $storyLogEntry): bool
    {
        $campaign = $this->resolveCampaign($storyLogEntry);

        if (! $campaign instanceof Campaign) {
            return false;
        }

        if (! $campaign->isVisibleTo($user)) {
            return false;
        }

        if ($campaign->canManageCampaign($user)) {
            return true;
        }

        return $storyLogEntry->isRevealed();
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $campaign->canManageCampaign($user);
    }

    public function update(User $user, StoryLogEntry $storyLogEntry): bool
    {
        $campaign = $this->resolveCampaign($storyLogEntry);

        return $campaign instanceof Campaign
            && $campaign->canManageCampaign($user);
    }

    public function delete(User $user, StoryLogEntry $storyLogEntry): bool
    {
        $campaign = $this->resolveCampaign($storyLogEntry);

        return $campaign instanceof Campaign
            && $campaign->canManageCampaign($user);
    }

    public function reveal(User $user, StoryLogEntry $storyLogEntry): bool
    {
        $campaign = $this->resolveCampaign($storyLogEntry);

        return $campaign instanceof Campaign
            && $campaign->canManageCampaign($user);
    }

    public function unreveal(User $user, StoryLogEntry $storyLogEntry): bool
    {
        $campaign = $this->resolveCampaign($storyLogEntry);

        return $campaign instanceof Campaign
            && $campaign->canManageCampaign($user);
    }

    private function resolveCampaign(StoryLogEntry $storyLogEntry): ?Campaign
    {
        if ($storyLogEntry->relationLoaded('campaign')) {
            $campaign = $storyLogEntry->getRelation('campaign');

            return $campaign instanceof Campaign ? $campaign : null;
        }

        /** @var Campaign|null $campaign */
        $campaign = Campaign::query()->find((int) $storyLogEntry->campaign_id);

        if ($campaign instanceof Campaign) {
            $storyLogEntry->setRelation('campaign', $campaign);
        }

        return $campaign;
    }
}
