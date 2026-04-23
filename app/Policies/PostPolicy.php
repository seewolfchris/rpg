<?php

namespace App\Policies;

use App\Models\Campaign;
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
        $campaign = $this->resolveCampaignFromPost($post);
        if (! $campaign instanceof Campaign) {
            return (int) $post->user_id === (int) $user->id;
        }

        return $campaign->isVisibleTo($user)
            || (int) $post->user_id === (int) $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Scene $scene): bool
    {
        $campaign = $this->resolveCampaignFromScene($scene);
        if (! $campaign instanceof Campaign) {
            return false;
        }

        if ($scene->status !== 'open' && ! $campaign->canModeratePosts($user)) {
            return false;
        }

        return $campaign->isVisibleTo($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Post $post): bool
    {
        $campaign = $this->resolveCampaignFromPost($post);
        if (! $campaign instanceof Campaign) {
            return (int) $post->user_id === (int) $user->id;
        }

        if (! $campaign->isVisibleTo($user)) {
            return false;
        }

        return (int) $post->user_id === (int) $user->id
            || $campaign->canModeratePosts($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Post $post): bool
    {
        $campaign = $this->resolveCampaignFromPost($post);
        if (! $campaign instanceof Campaign) {
            return (int) $post->user_id === (int) $user->id;
        }

        if (! $campaign->isVisibleTo($user)) {
            return false;
        }

        return (int) $post->user_id === (int) $user->id
            || $campaign->canModeratePosts($user);
    }

    public function moderate(User $user, Post $post): bool
    {
        $campaign = $this->resolveCampaignFromPost($post);

        return $campaign instanceof Campaign
            && $campaign->canModeratePosts($user);
    }

    private function resolveCampaignFromPost(Post $post): ?Campaign
    {
        $scene = $post->scene;
        if (! $scene instanceof Scene) {
            return null;
        }

        return $this->resolveCampaignFromScene($scene);
    }

    private function resolveCampaignFromScene(Scene $scene): ?Campaign
    {
        $campaign = $scene->campaign;

        return $campaign instanceof Campaign ? $campaign : null;
    }
}
