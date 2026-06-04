<?php

namespace App\Support;

use App\Models\CampaignInvitation;
use App\Models\Campaign;
use App\Models\SceneBookmark;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\Builder;

class NavigationCounters
{
    /**
     * @var array<string, array{unreadNotificationsCount: int, pendingCampaignInvitationsCount: int, bookmarkCount: int}>
     */
    private array $cache = [];

    /**
     * @var array<string, array{pendingCampaignInvitationsCount: int, activeSceneSubscriptionsCount: int, visibleBookmarkCount: int}>
     */
    private array $personalCenterCache = [];

    /**
     * @return array{unreadNotificationsCount: int, pendingCampaignInvitationsCount: int, bookmarkCount: int}
     */
    public function forUser(?User $user): array
    {
        if (! $user) {
            return $this->empty();
        }

        $cacheKey = 'user:'.$user->getKey();
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $counts = User::query()
            ->whereKey($user->getKey())
            ->withCount([
                'unreadNotifications',
                'campaignInvitations as pending_campaign_invitations_count' => fn (Builder $query) => $query
                    ->where('status', CampaignInvitation::STATUS_PENDING),
                'sceneBookmarks as visible_bookmarks_count' => function (Builder $bookmarkQuery) use ($user): void {
                    $bookmarkQuery->whereHas('scene.campaign', function (Builder $campaignQuery) use ($user): void {
                        $campaignQuery->whereIn('id', Campaign::query()->visibleTo($user)->select('id'));
                    });
                },
            ])
            ->first();

        if (! $counts) {
            return $this->empty();
        }

        $resolved = [
            'unreadNotificationsCount' => (int) ($counts->unread_notifications_count ?? 0),
            'pendingCampaignInvitationsCount' => (int) ($counts->pending_campaign_invitations_count ?? 0),
            'bookmarkCount' => (int) ($counts->visible_bookmarks_count ?? 0),
        ];

        $this->cache[$cacheKey] = $resolved;

        return $resolved;
    }

    /**
     * @return array{pendingCampaignInvitationsCount: int, activeSceneSubscriptionsCount: int, visibleBookmarkCount: int}
     */
    public function personalCenterForUser(?User $user, ?World $world): array
    {
        if (! $user || ! $world) {
            return [
                'pendingCampaignInvitationsCount' => 0,
                'activeSceneSubscriptionsCount' => 0,
                'visibleBookmarkCount' => 0,
            ];
        }

        $cacheKey = 'user:'.$user->getKey().':world:'.$world->getKey();
        if (isset($this->personalCenterCache[$cacheKey])) {
            return $this->personalCenterCache[$cacheKey];
        }

        $visibleCampaignIdsForWorld = Campaign::query()
            ->visibleTo($user)
            ->where('world_id', (int) $world->id)
            ->select('id');

        $activeSceneSubscriptionsCount = SceneSubscription::query()
            ->where('user_id', (int) $user->id)
            ->where('is_muted', false)
            ->whereHas('scene.campaign', function (Builder $campaignQuery) use ($visibleCampaignIdsForWorld): void {
                $campaignQuery->whereIn('id', $visibleCampaignIdsForWorld);
            })
            ->count();

        $visibleBookmarkCount = SceneBookmark::query()
            ->where('user_id', (int) $user->id)
            ->whereHas('scene.campaign', function (Builder $campaignQuery) use ($visibleCampaignIdsForWorld): void {
                $campaignQuery->whereIn('id', $visibleCampaignIdsForWorld);
            })
            ->count();

        $resolved = [
            'pendingCampaignInvitationsCount' => $this->forUser($user)['pendingCampaignInvitationsCount'],
            'activeSceneSubscriptionsCount' => $activeSceneSubscriptionsCount,
            'visibleBookmarkCount' => $visibleBookmarkCount,
        ];

        $this->personalCenterCache[$cacheKey] = $resolved;

        return $resolved;
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
