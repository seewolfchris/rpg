<?php

namespace App\Http\Controllers;

use App\Domain\Post\PostModerationService;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Post\BulkModerationRequest;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\World;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GmModerationController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly PostModerationService $postModerationService,
    ) {}

    public function index(Request $request, World $world): View
    {
        $status = in_array((string) $request->query('status', 'pending'), ['all', 'pending', 'approved', 'rejected'], true)
            ? (string) $request->query('status', 'pending')
            : 'pending';
        $search = trim((string) $request->query('q', ''));

        $baseQuery = Post::query()
            ->whereHas('scene.campaign', fn (Builder $campaignQuery) => $campaignQuery->where('world_id', (int) $world->id));

        $postsQuery = (clone $baseQuery)
            ->with(['scene.campaign', 'scene', 'user', 'character', 'approvedBy', 'latestModerationLog.moderator'])
            ->withCount('moderationLogs');

        $this->applyFilters($postsQuery, $status, $search);

        if ($status === 'all') {
            $postsQuery->orderByRaw("CASE moderation_status WHEN 'pending' THEN 0 WHEN 'rejected' THEN 1 ELSE 2 END");
        }

        $posts = $postsQuery
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $totalCount = (clone $baseQuery)->count();
        $pendingCount = (clone $baseQuery)->where('moderation_status', 'pending')->count();
        $approvedCount = (clone $baseQuery)->where('moderation_status', 'approved')->count();
        $rejectedCount = (clone $baseQuery)->where('moderation_status', 'rejected')->count();

        return view('gm.moderation', compact(
            'world',
            'posts',
            'status',
            'search',
            'totalCount',
            'pendingCount',
            'approvedCount',
            'rejectedCount',
        ));
    }

    public function bulkUpdate(BulkModerationRequest $request, World $world): View|RedirectResponse
    {
        $statusFilter = (string) $request->validated('status', 'pending');
        $search = trim((string) $request->validated('q', ''));
        $targetStatus = (string) $request->validated('moderation_status');
        $moderationNote = $this->normalizeModerationNote((string) $request->validated('moderation_note', ''));
        $moderator = $request->user();
        $sceneId = (int) $request->validated('scene_id', 0);
        $postIds = collect((array) $request->validated('post_ids', []))
            ->map(static fn ($postId): int => (int) $postId)
            ->filter(static fn (int $postId): bool => $postId > 0)
            ->unique()
            ->values();
        $isHtmxRequest = $request->header('HX-Request') === 'true';

        $postsQuery = Post::query()
            ->whereHas('scene.campaign', fn (Builder $campaignQuery) => $campaignQuery->where('world_id', (int) $world->id))
            ->with(['scene.campaign', 'user']);

        if ($postIds->isNotEmpty()) {
            $postsQuery->whereKey($postIds->all());
        } elseif ($isHtmxRequest && $sceneId > 0) {
            $postsQuery->whereRaw('1 = 0');
        } else {
            $this->applyFilters($postsQuery, $statusFilter, $search);
        }

        if ($sceneId > 0) {
            $postsQuery->where('scene_id', $sceneId);
        }

        $posts = $postsQuery->get();
        $affected = 0;

        foreach ($posts as $post) {
            $previousStatus = (string) $post->moderation_status;

            if ($previousStatus === $targetStatus && ! $moderationNote) {
                continue;
            }

            $post->moderation_status = $targetStatus;

            if ($targetStatus === 'approved') {
                $post->approved_at = now();
                $post->approved_by = $moderator->id;
            } else {
                $post->approved_at = null;
                $post->approved_by = null;
            }

            $post->save();

            $this->postModerationService->synchronize(
                post: $post,
                moderator: $moderator,
                previousStatus: $previousStatus,
                moderationNote: $moderationNote,
            );

            $affected++;
        }

        if ($isHtmxRequest && $sceneId > 0) {
            return $this->threadFeedFragment($request, $world, $sceneId);
        }

        return redirect()
            ->route('gm.moderation.index', [
                'world' => $world,
                'status' => $statusFilter,
                'q' => $search !== '' ? $search : null,
            ])
            ->with('status', 'Bulk-Moderation ausgeführt. Betroffene Posts: '.$affected.'.');
    }

    public function probe(Request $request, World $world, Post $post): View
    {
        $post->loadMissing(Post::WORLD_CONTEXT_RELATIONS);
        $this->ensurePostBelongsToWorld($world, $post);

        $data = $request->validate([
            'modifier' => ['nullable', 'integer', 'between:-20,20'],
        ]);

        $modifier = (int) ($data['modifier'] ?? 0);
        $roll = random_int(1, 20);
        $total = $roll + $modifier;
        $outcome = match (true) {
            $roll === 20 => 'Kritischer Erfolg',
            $roll === 1 => 'Kritischer Patzer',
            $total >= 15 => 'Erfolg',
            default => 'Fehlschlag',
        };

        return view('gm.partials.probe-result', compact('post', 'roll', 'modifier', 'total', 'outcome'));
    }

    private function applyFilters(Builder $query, string $status, string $search): void
    {
        if ($status !== 'all') {
            $query->where('moderation_status', $status);
        }

        if ($search !== '') {
            $searchTerm = '%'.$search.'%';
            $query->where(function (Builder $innerQuery) use ($searchTerm, $search): void {
                $innerQuery->where('content', 'like', $searchTerm)
                    ->orWhereHas('user', function (Builder $userQuery) use ($searchTerm): void {
                        $userQuery->where('name', 'like', $searchTerm);
                    })
                    ->orWhereHas('scene', function (Builder $sceneQuery) use ($searchTerm): void {
                        $sceneQuery->where('title', 'like', $searchTerm);
                    })
                    ->orWhereHas('scene.campaign', function (Builder $campaignQuery) use ($searchTerm): void {
                        $campaignQuery->where('title', 'like', $searchTerm);
                    })
                    ->orWhereHas('latestModerationLog', function (Builder $logQuery) use ($searchTerm): void {
                        $logQuery->where('reason', 'like', $searchTerm);
                    });

                if (is_numeric($search)) {
                    $innerQuery->orWhere('id', (int) $search);
                }
            });
        }
    }

    private function normalizeModerationNote(string $note): ?string
    {
        $normalized = trim($note);

        return $normalized !== '' ? $normalized : null;
    }

    private function threadFeedFragment(Request $request, World $world, int $sceneId): View
    {
        $scene = Scene::query()
            ->with('campaign')
            ->findOrFail($sceneId);

        $campaign = $scene->campaign;
        abort_unless((int) $campaign->world_id === (int) $world->id, 404);
        $this->authorize('view', $scene);

        $posts = Post::query()
            ->where('scene_id', $scene->id)
            ->with(Post::THREAD_PAGE_RELATIONS)
            ->latestByIdHotpath()
            ->paginate(Post::THREAD_POSTS_PER_PAGE)
            ->withQueryString();
        $subscription = SceneSubscription::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', (int) $request->user()->id)
            ->first();
        $latestPostId = $this->latestScenePostId($scene);
        $unreadPostsCount = $this->unreadCountForScene($scene, $subscription, $latestPostId);
        $canModerateScene = $request->user()->isGmOrAdmin() || $campaign->isCoGm($request->user());

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

    private function latestScenePostId(Scene $scene): int
    {
        return (int) Post::query()
            ->where('scene_id', $scene->id)
            ->max('id');
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
