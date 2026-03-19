<?php

namespace App\Policies;

use App\Models\PushSubscription;
use App\Models\User;
use App\Models\World;

class PushSubscriptionPolicy
{
    public function create(User $user, World $world): bool
    {
        return $world->is_active;
    }

    public function delete(User $user, PushSubscription $subscription): bool
    {
        return (int) $subscription->user_id === (int) $user->id;
    }
}
