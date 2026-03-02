<?php

namespace App\Http\Controllers;

use App\Http\Requests\SceneSubscription\BulkUpdateSceneSubscriptionRequest;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SceneSubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $status = in_array((string) $request->query('status', 'all'), ['all', 'active', 'muted'], true)
            ? (string) $request->query('status', 'all')
            : 'all';
        $search = trim((string) $request->query('q', ''));

        $baseQuery = $this->visibleSubscriptionsQuery($user);

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
        $unreadCount = $this->unreadCountForUser((int) $user->id);

        return view('scene-subscriptions.index', compact(
            'subscriptions',
            'status',
            'search',
            'totalCount',
            'activeCount',
            'mutedCount',
            'unreadCount',
        ));
    }

    public function bulkUpdate(BulkUpdateSceneSubscriptionRequest $request): RedirectResponse
    {
        $user = $request->user();
        $action = (string) $request->validated('bulk_action');
        $status = (string) $request->validated('status', 'all');
        $search = trim((string) $request->validated('q', ''));

        $filteredQuery = $this->visibleSubscriptionsQuery($user);
        $this->applyFilters($filteredQuery, $status, $search);

        $visibleAllQuery = $this->visibleSubscriptionsQuery($user);

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
            default => 'Bulk-Aktion ausgefuehrt.',
        };

        return redirect()
            ->route('scene-subscriptions.index', [
                'status' => $status,
                'q' => $search !== '' ? $search : null,
            ])
            ->with('status', $message.' Betroffene Abos: '.$affected.'.');
    }

    public function subscribe(Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToCampaign($campaign, $scene);
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

    public function unsubscribe(Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToCampaign($campaign, $scene);
        $this->authorize('view', $scene);

        SceneSubscription::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', auth()->id())
            ->delete();

        return back()->with('status', 'Szenen-Abo entfernt.');
    }

    public function toggleMute(Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToCampaign($campaign, $scene);
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

    public function markRead(Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToCampaign($campaign, $scene);
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

            return back()->with('status', 'Szene als gelesen markiert.');
        }

        $subscription->last_read_at = now();
        $subscription->save();

        return back()->with('status', 'Szene enthaelt noch keine Beitraege.');
    }

    public function markUnread(Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToCampaign($campaign, $scene);
        $this->authorize('view', $scene);

        $subscription = SceneSubscription::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', auth()->id())
            ->first();

        if (! $subscription) {
            return back()->with('status', 'Szene ist nicht abonniert.');
        }

        $subscription->markUnread();

        return back()->with('status', 'Szene als ungelesen markiert.');
    }

    private function ensureSceneBelongsToCampaign(Campaign $campaign, Scene $scene): void
    {
        abort_unless($scene->campaign_id === $campaign->id, 404);
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

    private function unreadCountForUser(int $userId): int
    {
        $latestPostsPerScene = Post::query()
            ->selectRaw('scene_id, MAX(id) as latest_post_id')
            ->groupBy('scene_id');

        return (int) SceneSubscription::query()
            ->leftJoinSub($latestPostsPerScene, 'latest_posts', function ($join): void {
                $join->on('scene_subscriptions.scene_id', '=', 'latest_posts.scene_id');
            })
            ->where('scene_subscriptions.user_id', $userId)
            ->whereNotNull('latest_posts.latest_post_id')
            ->where(function ($query): void {
                $query->whereNull('scene_subscriptions.last_read_post_id')
                    ->orWhereColumn('scene_subscriptions.last_read_post_id', '<', 'latest_posts.latest_post_id');
            })
            ->count();
    }

    private function visibleSubscriptionsQuery(User $user): Builder
    {
        return SceneSubscription::query()
            ->where('user_id', $user->id)
            ->whereHas('scene.campaign', fn (Builder $campaignQuery) => $campaignQuery->visibleTo($user));
    }
}
