<?php

namespace App\Domain\Post;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Models\Post;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\Builder;

class PostModerationScope
{
    public function __construct(
        private readonly CampaignParticipantResolver $campaignParticipantResolver,
    ) {}

    public function canAccessWorldQueue(User $user, World $world): bool
    {
        return $this->campaignParticipantResolver
            ->canModerateWorldQueue($user, $world);
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

        if ($user->isAdmin()) {
            return $query;
        }

        $coGmCampaignIds = $this->campaignParticipantResolver
            ->moderatableCampaignIdsForWorld($user, $world);

        if ($coGmCampaignIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('scene', function (Builder $sceneQuery) use ($coGmCampaignIds): void {
            $sceneQuery->whereIn('campaign_id', $coGmCampaignIds->all());
        });
    }
}
