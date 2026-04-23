<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\Scene;
use App\Models\User;

class ScenePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Scene $scene): bool
    {
        $campaign = $this->resolveCampaign($scene);
        if (! $campaign instanceof Campaign) {
            return false;
        }

        $canViewCampaign = $campaign->isVisibleTo($user);

        if (! $canViewCampaign) {
            return false;
        }

        if ($scene->status !== 'archived') {
            return true;
        }

        return $campaign->canManageCampaign($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Campaign $campaign): bool
    {
        return $campaign->canManageCampaign($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Scene $scene): bool
    {
        $campaign = $this->resolveCampaign($scene);
        if (! $campaign instanceof Campaign) {
            return false;
        }

        return $campaign->canManageCampaign($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Scene $scene): bool
    {
        $campaign = $this->resolveCampaign($scene);
        if (! $campaign instanceof Campaign) {
            return false;
        }

        return $campaign->canManageCampaign($user);
    }

    private function resolveCampaign(Scene $scene): ?Campaign
    {
        $campaign = $scene->campaign;

        return $campaign instanceof Campaign ? $campaign : null;
    }
}
