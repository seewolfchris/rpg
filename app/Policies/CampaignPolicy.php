<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
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
    public function view(User $user, Campaign $campaign): bool
    {
        return $campaign->isVisibleTo($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isGmOrAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Campaign $campaign): bool
    {
        return $campaign->owner_id === $user->id
            || $user->isGmOrAdmin()
            || $campaign->isCoGm($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Campaign $campaign): bool
    {
        return $campaign->owner_id === $user->id || $user->isGmOrAdmin();
    }
}
