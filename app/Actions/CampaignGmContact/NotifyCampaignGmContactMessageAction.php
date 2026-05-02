<?php

namespace App\Actions\CampaignGmContact;

use App\Domain\Campaign\CampaignAccess;
use App\Models\Campaign;
use App\Models\CampaignGmContactThread;
use App\Models\User;
use App\Notifications\CampaignGmContactMessageNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotifyCampaignGmContactMessageAction
{
    public function __construct(
        private readonly CampaignAccess $campaignAccess,
    ) {}

    public function execute(CampaignGmContactThread $thread, User $author, string $content): void
    {
        $thread->loadMissing('campaign.world');
        $campaign = $thread->campaign;

        if (! $campaign instanceof Campaign) {
            return;
        }

        $recipients = $this->recipients($thread, $campaign, $author);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new CampaignGmContactMessageNotification(
            thread: $thread,
            author: $author,
            content: $content,
        ));
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(CampaignGmContactThread $thread, Campaign $campaign, User $author): Collection
    {
        $authorId = (int) $author->id;

        if ($this->campaignAccess->isCampaignContactGmSide($campaign, $author)) {
            $creatorId = (int) $thread->created_by;

            if ($creatorId <= 0 || $creatorId === $authorId) {
                return collect();
            }

            return User::query()
                ->whereKey($creatorId)
                ->get();
        }

        $recipientIds = collect([(int) $campaign->owner_id])
            ->merge($this->campaignAccess->gmContactCoGmRecipientIds($campaign))
            ->filter(static fn (int $userId): bool => $userId > 0)
            ->reject(static fn (int $userId): bool => $userId === $authorId)
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $recipientIds)
            ->orderBy('id')
            ->get();
    }
}
