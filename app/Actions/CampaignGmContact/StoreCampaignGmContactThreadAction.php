<?php

namespace App\Actions\CampaignGmContact;

use App\Models\Campaign;
use App\Models\CampaignGmContactMessage;
use App\Models\CampaignGmContactThread;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

class StoreCampaignGmContactThreadAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly NotifyCampaignGmContactMessageAction $notifyCampaignGmContactMessageAction,
    ) {}

    /**
     * @param  array{
     *   subject: string,
     *   content: string,
     *   character_id?: int|null,
     *   scene_id?: int|null
     * }  $data
     */
    public function execute(Campaign $campaign, User $author, array $data): CampaignGmContactThread
    {
        $content = (string) $data['content'];

        /** @var CampaignGmContactThread $thread */
        $thread = $this->db->transaction(function () use ($campaign, $author, $data, $content): CampaignGmContactThread {
            $now = now();
            $status = CampaignGmContactThread::isGmSide($campaign, $author)
                ? CampaignGmContactThread::STATUS_WAITING_FOR_PLAYER
                : CampaignGmContactThread::STATUS_WAITING_FOR_GM;

            /** @var CampaignGmContactThread $thread */
            $thread = CampaignGmContactThread::query()->create([
                'campaign_id' => (int) $campaign->id,
                'created_by' => (int) $author->id,
                'subject' => (string) $data['subject'],
                'status' => $status,
                'character_id' => array_key_exists('character_id', $data)
                    ? (is_numeric($data['character_id']) ? (int) $data['character_id'] : null)
                    : null,
                'scene_id' => array_key_exists('scene_id', $data)
                    ? (is_numeric($data['scene_id']) ? (int) $data['scene_id'] : null)
                    : null,
                'last_activity_at' => $now,
            ]);

            CampaignGmContactMessage::query()->create([
                'thread_id' => (int) $thread->id,
                'user_id' => (int) $author->id,
                'content' => $content,
            ]);

            return $thread;
        }, 3);

        $thread->loadMissing(['campaign.world', 'creator']);
        $this->notifyCampaignGmContactMessageAction->execute($thread, $author, $content);

        return $thread;
    }
}
