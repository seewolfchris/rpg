<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Campaign;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\Builder;

trait BuildsVisibleCampaignSubquery
{
    /**
     * @return Builder<Campaign>
     */
    protected function visibleCampaignIdsSubquery(User $user): Builder
    {
        return Campaign::query()
            ->visibleTo($user)
            ->select('id');
    }

    /**
     * @return Builder<Campaign>
     */
    protected function visibleCampaignIdsSubqueryForWorld(User $user, World $world): Builder
    {
        return $this->visibleCampaignIdsSubquery($user)
            ->where('world_id', (int) $world->id);
    }
}
