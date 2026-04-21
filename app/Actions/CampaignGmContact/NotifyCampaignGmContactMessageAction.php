<?php

namespace App\Actions\CampaignGmContact;

use App\Models\Campaign;
use App\Models\CampaignGmContactThread;
use App\Models\CampaignInvitation;
use App\Models\User;
use App\Notifications\CampaignGmContactMessageNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotifyCampaignGmContactMessageAction
{
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

        if (CampaignGmContactThread::isGmSide($campaign, $author)) {
            $creatorId = (int) $thread->created_by;

            if ($creatorId <= 0 || $creatorId === $authorId) {
                return collect();
            }

            return User::query()
                ->whereKey($creatorId)
                ->get();
        }

        $coGmIds = $campaign->invitations()
            ->where('status', CampaignInvitation::STATUS_ACCEPTED)
            ->where('role', CampaignInvitation::ROLE_CO_GM)
            ->pluck('user_id')
            ->map(static fn ($userId): int => (int) $userId);

        $recipientIds = collect([(int) $campaign->owner_id])
            ->merge($coGmIds)
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
