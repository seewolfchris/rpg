<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\Scene;
use App\Models\User;

class PostPolicy
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
    public function view(User $user, Post $post): bool
    {
        $scene = $post->scene;
        $campaign = $scene->campaign;

        return $campaign->isVisibleTo($user)
            || $post->user_id === $user->id
            || $user->isGmOrAdmin();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Scene $scene): bool
    {
        if ($scene->status !== 'open' && ! $user->isGmOrAdmin() && ! $scene->campaign->isCoGm($user)) {
            return false;
        }

        return $scene->campaign->isVisibleTo($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Post $post): bool
    {
        return $post->user_id === $user->id
            || $user->isGmOrAdmin()
            || $post->scene->campaign->isCoGm($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Post $post): bool
    {
        return $post->user_id === $user->id
            || $user->isGmOrAdmin()
            || $post->scene->campaign->isCoGm($user);
    }

    public function moderate(User $user, Post $post): bool
    {
        return $user->isGmOrAdmin() || $post->scene->campaign->isCoGm($user);
    }
}
