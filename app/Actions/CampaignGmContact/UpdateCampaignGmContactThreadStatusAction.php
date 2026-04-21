<?php

namespace App\Actions\CampaignGmContact;

use App\Models\CampaignGmContactThread;

class UpdateCampaignGmContactThreadStatusAction
{
    public function execute(CampaignGmContactThread $thread, string $status): CampaignGmContactThread
    {
        $thread->status = $status;
        $thread->last_activity_at = now();
        $thread->save();

        $freshThread = $thread->fresh();

        return $freshThread instanceof CampaignGmContactThread ? $freshThread : $thread;
    }
}
