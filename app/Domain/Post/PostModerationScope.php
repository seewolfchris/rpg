<?php

namespace App\Domain\Post;

use App\Models\CampaignInvitation;
use App\Models\Post;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\Builder;

class PostModerationScope
{
    public function canAccessWorldQueue(User $user, World $world): bool
    {
        if ($user->isGmOrAdmin()) {
            return true;
        }

        return CampaignInvitation::query()
            ->where('user_id', (int) $user->id)
            ->where('status', CampaignInvitation::STATUS_ACCEPTED)
            ->where('role', CampaignInvitation::ROLE_CO_GM)
            ->whereHas('campaign', function (Builder $campaignQuery) use ($world): void {
                $campaignQuery->where('world_id', (int) $world->id);
            })
            ->exists();
    }

    /**
     * @return Builder<Post>
     */
    public function baseQuery(User $user, World $world): Builder
    {
        $query = Post::query()
            ->whereHas('scene.campaign', function (Builder $campaignQuery) use ($world): void {
                $campaignQuery->where('world_id', (int) $world->id);
            });

        if ($user->isGmOrAdmin()) {
            return $query;
        }

        return $query->whereHas('scene.campaign.invitations', function (Builder $invitationQuery) use ($user): void {
            $invitationQuery
                ->where('user_id', (int) $user->id)
                ->where('status', CampaignInvitation::STATUS_ACCEPTED)
                ->where('role', CampaignInvitation::ROLE_CO_GM);
        });
    }
}
