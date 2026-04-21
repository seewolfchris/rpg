<?php

namespace App\Actions\CampaignGmContact;

use App\Models\Campaign;
use App\Models\CampaignGmContactMessage;
use App\Models\CampaignGmContactThread;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

class StoreCampaignGmContactMessageAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly NotifyCampaignGmContactMessageAction $notifyCampaignGmContactMessageAction,
    ) {}

    /**
     * @param  array{content: string}  $data
     */
    public function execute(CampaignGmContactThread $thread, User $author, array $data): CampaignGmContactMessage
    {
        $thread->loadMissing('campaign.world');
        $campaign = $thread->campaign;

        if (! $campaign instanceof Campaign) {
            abort(404);
        }

        $content = (string) $data['content'];

        /** @var CampaignGmContactMessage $message */
        $message = $this->db->transaction(function () use ($thread, $author, $campaign, $content): CampaignGmContactMessage {
            $status = CampaignGmContactThread::isGmSide($campaign, $author)
                ? CampaignGmContactThread::STATUS_WAITING_FOR_PLAYER
                : CampaignGmContactThread::STATUS_WAITING_FOR_GM;

            /** @var CampaignGmContactMessage $message */
            $message = CampaignGmContactMessage::query()->create([
                'thread_id' => (int) $thread->id,
                'user_id' => (int) $author->id,
                'content' => $content,
            ]);

            $thread->status = $status;
            $thread->last_activity_at = $message->created_at;
            $thread->save();

            return $message;
        }, 3);

        $freshThread = $thread->fresh();
        if ($freshThread instanceof CampaignGmContactThread) {
            $this->notifyCampaignGmContactMessageAction->execute($freshThread, $author, $content);
        }

        return $message;
    }
}
