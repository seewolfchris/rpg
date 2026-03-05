<?php

namespace App\Support;

use App\Models\CampaignInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class NavigationCounters
{
    /**
     * @return array{unreadNotificationsCount: int, pendingCampaignInvitationsCount: int, bookmarkCount: int}
     */
    public function forUser(?User $user): array
    {
        if (! $user) {
            return $this->empty();
        }

        $counts = User::query()
            ->whereKey($user->getKey())
            ->withCount([
                'unreadNotifications',
                'campaignInvitations as pending_campaign_invitations_count' => fn (Builder $query) => $query
                    ->where('status', CampaignInvitation::STATUS_PENDING),
                'sceneBookmarks as visible_bookmarks_count' => fn (Builder $bookmarkQuery) => $bookmarkQuery
                    ->whereHas('scene.campaign', fn (Builder $campaignQuery) => $campaignQuery->visibleTo($user)),
            ])
            ->first();

        if (! $counts) {
            return $this->empty();
        }

        return [
            'unreadNotificationsCount' => (int) ($counts->unread_notifications_count ?? 0),
            'pendingCampaignInvitationsCount' => (int) ($counts->pending_campaign_invitations_count ?? 0),
            'bookmarkCount' => (int) ($counts->visible_bookmarks_count ?? 0),
        ];
    }

    /**
     * @return array{unreadNotificationsCount: int, pendingCampaignInvitationsCount: int, bookmarkCount: int}
     */
    private function empty(): array
    {
        return [
            'unreadNotificationsCount' => 0,
            'pendingCampaignInvitationsCount' => 0,
            'bookmarkCount' => 0,
        ];
    }
}
