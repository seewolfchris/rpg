<?php

namespace App\Http\Controllers;

use App\Actions\Scene\BuildSceneThreadPageDataAction;
use App\Actions\SceneSubscription\BulkUpdateSceneSubscriptionsAction;
use App\Actions\SceneSubscription\MarkSceneSubscriptionReadAction;
use App\Actions\SceneSubscription\MarkSceneSubscriptionUnreadAction;
use App\Actions\SceneSubscription\SubscribeSceneSubscriptionAction;
use App\Actions\SceneSubscription\ToggleSceneSubscriptionMuteAction;
use App\Actions\SceneSubscription\UnsubscribeSceneSubscriptionAction;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\SceneSubscription\BulkUpdateSceneSubscriptionRequest;
use App\Models\Campaign;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SceneSubscriptionController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly BuildSceneThreadPageDataAction $buildSceneThreadPageDataAction,
        private readonly BulkUpdateSceneSubscriptionsAction $bulkUpdateSceneSubscriptionsAction,
        private readonly SubscribeSceneSubscriptionAction $subscribeSceneSubscriptionAction,
        private readonly UnsubscribeSceneSubscriptionAction $unsubscribeSceneSubscriptionAction,
        private readonly ToggleSceneSubscriptionMuteAction $toggleSceneSubscriptionMuteAction,
        private readonly MarkSceneSubscriptionReadAction $markSceneSubscriptionReadAction,
        private readonly MarkSceneSubscriptionUnreadAction $markSceneSubscriptionUnreadAction,
    ) {}

    public function index(Request $request, World $world): View
    {
        $user = $this->authenticatedUser($request);
        $status = in_array((string) $request->query('status', 'all'), ['all', 'active', 'muted'], true)
            ? (string) $request->query('status', 'all')
            : 'all';
        $search = trim((string) $request->query('q', ''));
        $visibleCampaignIds = $this->visibleCampaignIdsForUserInWorld($user, $world);

        $baseQuery = $this->visibleSubscriptionsQuery($user, $visibleCampaignIds);

        $subscriptionsQuery = (clone $baseQuery)
            ->with([
                'scene' => fn ($sceneQuery) => $sceneQuery
                    ->with('campaign')
                    ->withCount('posts')
                    ->withMax('posts as latest_post_id', 'id')
                    ->withMax('posts as latest_post_created_at', 'created_at'),
            ])
            ->latest('updated_at');
        $this->applyFilters($subscriptionsQuery, $status, $search);

        $subscriptions = $subscriptionsQuery
            ->paginate(20)
            ->withQueryString();

        $counts = (array) ((clone $baseQuery)
            ->toBase()
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN is_muted = 0 THEN 1 ELSE 0 END) as active_count')
            ->selectRaw('SUM(CASE WHEN is_muted = 1 THEN 1 ELSE 0 END) as muted_count')
            ->first() ?? []);
        $totalCount = (int) ($counts['total_count'] ?? 0);
        $activeCount = (int) ($counts['active_count'] ?? 0);
        $mutedCount = (int) ($counts['muted_count'] ?? 0);
        $unreadCount = $this->unreadCountForUser((int) $user->id, $world);

        return view('scene-subscriptions.index', compact(
            'world',
            'subscriptions',
            'status',
            'search',
            'totalCount',
            'activeCount',
            'mutedCount',
            'unreadCount',
        ));
    }

    public function bulkUpdate(BulkUpdateSceneSubscriptionRequest $request, World $world): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $action = (string) $request->validated('bulk_action');
        $status = (string) $request->validated('status', 'all');
        $search = trim((string) $request->validated('q', ''));
        $result = $this->bulkUpdateSceneSubscriptionsAction->execute(
            user: $user,
            world: $world,
            action: $action,
            status: $status,
            search: $search,
        );

        return redirect()
            ->route('scene-subscriptions.index', [
                'world' => $world,
                'status' => $status,
                'q' => $search !== '' ? $search : null,
            ])
            ->with('status', $result->statusMessage());
    }

    public function subscribe(Request $request, World $world, Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);
        $user = $this->authenticatedUser($request);
        $result = $this->subscribeSceneSubscriptionAction->execute($user, $scene);

        return back()->with('status', $result->statusMessage());
    }

    public function unsubscribe(Request $request, World $world, Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);
        $user = $this->authenticatedUser($request);
        $result = $this->unsubscribeSceneSubscriptionAction->execute($user, $scene);

        return back()->with('status', $result->statusMessage());
    }

    public function toggleMute(Request $request, World $world, Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);
        $user = $this->authenticatedUser($request);
        $result = $this->toggleSceneSubscriptionMuteAction->execute($user, $scene);

        return back()->with('status', $result->statusMessage());
    }

    public function markRead(Request $request, World $world, Campaign $campaign, Scene $scene): View|RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);
        $user = $this->authenticatedUser($request);
        $result = $this->markSceneSubscriptionReadAction->execute($user, $scene);

        if ($request->header('HX-Request') === 'true') {
            return $this->threadFeedFragment($request, $scene, $campaign);
        }

        return back()->with('status', $result->statusMessage());
    }

    public function markUnread(Request $request, World $world, Campaign $campaign, Scene $scene): View|RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);
        $user = $this->authenticatedUser($request);
        $result = $this->markSceneSubscriptionUnreadAction->execute($user, $scene);

        if ($request->header('HX-Request') === 'true') {
            return $this->threadFeedFragment($request, $scene, $campaign);
        }

        return back()->with('status', $result->statusMessage());
    }

    /**
     * @param  Builder<SceneSubscription>  $query
     */
    private function applyFilters(Builder $query, string $status, string $search): void
    {
        if ($status === 'active') {
            $query->where('is_muted', false);
        } elseif ($status === 'muted') {
            $query->where('is_muted', true);
        }

        if ($search !== '') {
            $searchTerm = '%'.$search.'%';
            $query->where(function (Builder $innerQuery) use ($searchTerm): void {
                $innerQuery->whereHas('scene', function (Builder $sceneQuery) use ($searchTerm): void {
                    $sceneQuery->where('title', 'like', $searchTerm);
                })->orWhereHas('scene.campaign', function (Builder $campaignQuery) use ($searchTerm): void {
                    $campaignQuery->where('title', 'like', $searchTerm);
                });
            });
        }
    }

    private function unreadCountForUser(int $userId, World $world): int
    {
        return (int) SceneSubscription::query()
            ->join('scenes', 'scenes.id', '=', 'scene_subscriptions.scene_id')
            ->join('campaigns', 'campaigns.id', '=', 'scenes.campaign_id')
            ->where('scene_subscriptions.user_id', $userId)
            ->where('campaigns.world_id', (int) $world->id)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('posts')
                    ->whereColumn('posts.scene_id', 'scene_subscriptions.scene_id')
                    ->whereRaw('posts.id > COALESCE(scene_subscriptions.last_read_post_id, 0)');
            })
            ->count();
    }

    /**
     * @param  list<int>  $visibleCampaignIds
     * @return Builder<SceneSubscription>
     */
    private function visibleSubscriptionsQuery(User $user, array $visibleCampaignIds): Builder
    {
        return SceneSubscription::query()
            ->where('user_id', $user->id)
            ->whereHas('scene', function (Builder $sceneQuery) use ($visibleCampaignIds): void {
                $sceneQuery->whereIn('campaign_id', $visibleCampaignIds);
            });
    }

    /**
     * @return list<int>
     */
    private function visibleCampaignIdsForUserInWorld(User $user, World $world): array
    {
        /** @var list<int> $campaignIds */
        $campaignIds = Campaign::query()
            ->visibleTo($user)
            ->where('world_id', (int) $world->id)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return $campaignIds;
    }

    private function threadFeedFragment(
        Request $request,
        Scene $scene,
        Campaign $campaign,
    ): View {
        $user = $this->authenticatedUser($request);
        $threadPageData = $this->buildSceneThreadPageDataAction->execute(
            scene: $scene,
            campaign: $campaign,
            user: $user,
        );

        return view('scenes.partials.thread-page', [
            'posts' => $threadPageData->posts,
            'campaign' => $campaign,
            'scene' => $scene,
            'subscription' => $threadPageData->subscription,
            'latestPostId' => $threadPageData->latestPostId,
            'unreadPostsCount' => $threadPageData->unreadPostsCount,
            'canModerateScene' => $threadPageData->canModerateScene,
        ]);
    }
}
