<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\CampaignGmContactThread;
use App\Models\User;

class CampaignGmContactThreadPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return CampaignGmContactThread::hasCampaignContactAccess($campaign, $user);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return CampaignGmContactThread::hasCampaignContactAccess($campaign, $user);
    }

    public function view(User $user, CampaignGmContactThread $thread): bool
    {
        $campaign = $this->resolveCampaign($thread);

        if (! $campaign instanceof Campaign) {
            return false;
        }

        if (! CampaignGmContactThread::hasCampaignContactAccess($campaign, $user)) {
            return false;
        }

        if (CampaignGmContactThread::isGmSide($campaign, $user)) {
            return true;
        }

        return (int) $thread->created_by === (int) $user->id;
    }

    public function reply(User $user, CampaignGmContactThread $thread): bool
    {
        if (! $this->view($user, $thread)) {
            return false;
        }

        return ! $thread->isClosed();
    }

    public function updateStatus(User $user, CampaignGmContactThread $thread): bool
    {
        $campaign = $this->resolveCampaign($thread);

        if (! $campaign instanceof Campaign) {
            return false;
        }

        return CampaignGmContactThread::isGmSide($campaign, $user);
    }

    private function resolveCampaign(CampaignGmContactThread $thread): ?Campaign
    {
        if ($thread->relationLoaded('campaign')) {
            $campaign = $thread->getRelation('campaign');

            return $campaign instanceof Campaign ? $campaign : null;
        }

        /** @var Campaign|null $campaign */
        $campaign = Campaign::query()->find((int) $thread->campaign_id);

        if ($campaign instanceof Campaign) {
            $thread->setRelation('campaign', $campaign);
        }

        return $campaign;
    }
}
