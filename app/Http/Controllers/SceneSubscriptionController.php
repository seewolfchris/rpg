<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\SceneSubscription\BulkUpdateSceneSubscriptionRequest;
use App\Models\Campaign;
use App\Models\Post;
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

    public function index(Request $request, World $world): View
    {
        $user = $request->user();
        $status = in_array((string) $request->query('status', 'all'), ['all', 'active', 'muted'], true)
            ? (string) $request->query('status', 'all')
            : 'all';
        $search = trim((string) $request->query('q', ''));

        $baseQuery = $this->visibleSubscriptionsQuery($user, $world);

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

        $totalCount = (clone $baseQuery)->count();
        $activeCount = (clone $baseQuery)->where('is_muted', false)->count();
        $mutedCount = (clone $baseQuery)->where('is_muted', true)->count();
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
        $user = $request->user();
        $action = (string) $request->validated('bulk_action');
        $status = (string) $request->validated('status', 'all');
        $search = trim((string) $request->validated('q', ''));

        $filteredQuery = $this->visibleSubscriptionsQuery($user, $world);
        $this->applyFilters($filteredQuery, $status, $search);

        $visibleAllQuery = $this->visibleSubscriptionsQuery($user, $world);

        $affected = match ($action) {
            'mute_filtered' => (clone $filteredQuery)
                ->where('is_muted', false)
                ->update(['is_muted' => true, 'updated_at' => now()]),
            'unmute_filtered' => (clone $filteredQuery)
                ->where('is_muted', true)
                ->update(['is_muted' => false, 'updated_at' => now()]),
            'unfollow_filtered' => (clone $filteredQuery)->delete(),
            'mute_all_active' => (clone $visibleAllQuery)
                ->where('is_muted', false)
                ->update(['is_muted' => true, 'updated_at' => now()]),
            'unmute_all_muted' => (clone $visibleAllQuery)
                ->where('is_muted', true)
                ->update(['is_muted' => false, 'updated_at' => now()]),
            'unfollow_all_muted' => (clone $visibleAllQuery)
                ->where('is_muted', true)
                ->delete(),
            default => 0,
        };

        $message = match ($action) {
            'mute_filtered' => 'Gefilterte Abos stummgeschaltet.',
            'unmute_filtered' => 'Gefilterte Abos aktiviert.',
            'unfollow_filtered' => 'Gefilterte Abos entfernt.',
            'mute_all_active' => 'Alle aktiven Abos stummgeschaltet.',
            'unmute_all_muted' => 'Alle stummen Abos aktiviert.',
            'unfollow_all_muted' => 'Alle stummen Abos entfernt.',
            default => 'Bulk-Aktion ausgeführt.',
        };

        return redirect()
            ->route('scene-subscriptions.index', [
                'world' => $world,
                'status' => $status,
                'q' => $search !== '' ? $search : null,
            ])
            ->with('status', $message.' Betroffene Abos: '.$affected.'.');
    }

    public function subscribe(World $world, Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);

        $latestPostId = $this->latestScenePostId($scene);

        SceneSubscription::query()->updateOrCreate([
            'scene_id' => $scene->id,
            'user_id' => auth()->id(),
        ], [
            'is_muted' => false,
            'last_read_post_id' => $latestPostId > 0 ? $latestPostId : null,
            'last_read_at' => now(),
        ]);

        return back()->with('status', 'Szene abonniert.');
    }

    public function unsubscribe(World $world, Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);

        SceneSubscription::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', auth()->id())
            ->delete();

        return back()->with('status', 'Szenen-Abo entfernt.');
    }

    public function toggleMute(World $world, Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);

        $latestPostId = $this->latestScenePostId($scene);

        $subscription = SceneSubscription::query()->firstOrCreate([
            'scene_id' => $scene->id,
            'user_id' => auth()->id(),
        ], [
            'is_muted' => false,
            'last_read_post_id' => $latestPostId > 0 ? $latestPostId : null,
            'last_read_at' => now(),
        ]);

        $subscription->is_muted = ! $subscription->is_muted;
        $subscription->save();

        return back()->with(
            'status',
            $subscription->is_muted
                ? 'Szenen-Benachrichtigungen stummgeschaltet.'
                : 'Szenen-Benachrichtigungen aktiviert.',
        );
    }

    public function markRead(Request $request, World $world, Campaign $campaign, Scene $scene): View|RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);

        $latestPostId = $this->latestScenePostId($scene);

        $subscription = SceneSubscription::query()->firstOrCreate([
            'scene_id' => $scene->id,
            'user_id' => auth()->id(),
        ], [
            'is_muted' => false,
            'last_read_post_id' => null,
            'last_read_at' => null,
        ]);

        if ($latestPostId > 0) {
            $subscription->markRead($latestPostId);

            if ($request->header('HX-Request') === 'true') {
                return $this->threadFeedFragment($request, $scene, $campaign, $subscription);
            }

            return back()->with('status', 'Szene als gelesen markiert.');
        }

        $subscription->last_read_at = now();
        $subscription->save();

        if ($request->header('HX-Request') === 'true') {
            return $this->threadFeedFragment($request, $scene, $campaign, $subscription);
        }

        return back()->with('status', 'Szene enthält noch keine Beiträge.');
    }

    public function markUnread(Request $request, World $world, Campaign $campaign, Scene $scene): View|RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);

        $subscription = SceneSubscription::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', auth()->id())
            ->first();

        if (! $subscription) {
            if ($request->header('HX-Request') === 'true') {
                return $this->threadFeedFragment($request, $scene, $campaign, null);
            }

            return back()->with('status', 'Szene ist nicht abonniert.');
        }

        $subscription->markUnread();

        if ($request->header('HX-Request') === 'true') {
            return $this->threadFeedFragment($request, $scene, $campaign, $subscription->fresh());
        }

        return back()->with('status', 'Szene als ungelesen markiert.');
    }

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

    private function latestScenePostId(Scene $scene): int
    {
        return (int) Post::query()
            ->where('scene_id', $scene->id)
            ->max('id');
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

    private function visibleSubscriptionsQuery(User $user, World $world): Builder
    {
        return SceneSubscription::query()
            ->where('user_id', $user->id)
            ->whereHas('scene.campaign', function (Builder $campaignQuery) use ($user, $world): void {
                $campaignQuery->whereIn('id', Campaign::query()
                    ->visibleTo($user)
                    ->where('world_id', (int) $world->id)
                    ->select('id'));
            });
    }

    private function threadFeedFragment(
        Request $request,
        Scene $scene,
        Campaign $campaign,
        ?SceneSubscription $subscription,
    ): View {
        $user = $request->user();
        $posts = Post::query()
            ->where('scene_id', $scene->id)
            ->with(Post::THREAD_PAGE_RELATIONS)
            ->latestByIdHotpath()
            ->paginate(Post::THREAD_POSTS_PER_PAGE)
            ->withQueryString();
        $latestPostId = $this->latestScenePostId($scene);
        $unreadPostsCount = $this->unreadCountForScene($scene, $subscription, $latestPostId);
        $canModerateScene = $user->isGmOrAdmin() || $campaign->isCoGm($user);

        return view('scenes.partials.thread-page', compact(
            'posts',
            'campaign',
            'scene',
            'subscription',
            'latestPostId',
            'unreadPostsCount',
            'canModerateScene',
        ));
    }

    private function unreadCountForScene(Scene $scene, ?SceneSubscription $subscription, int $latestPostId): int
    {
        if (! $subscription || $latestPostId <= 0 || ! $subscription->hasUnread($latestPostId)) {
            return 0;
        }

        return (int) Post::query()
            ->where('scene_id', $scene->id)
            ->where('id', '>', (int) ($subscription->last_read_post_id ?? 0))
            ->count();
    }
}
