<?php

namespace App\Http\Controllers;

use App\Actions\Post\BulkModeratePostsAction;
use App\Actions\Post\BulkModeratePostsInput;
use App\Domain\Post\PostModerationScope;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Post\BulkModerationRequest;
use App\Models\Campaign;
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
        private readonly BulkModeratePostsAction $bulkModeratePostsAction,
        private readonly PostModerationScope $postModerationScope,
    ) {}

    public function index(Request $request, World $world): View
    {
        $user = $request->user();
        abort_unless($user && $this->postModerationScope->canAccessWorldQueue($user, $world), 403);

        $status = in_array((string) $request->query('status', 'pending'), ['all', 'pending', 'approved', 'rejected'], true)
            ? (string) $request->query('status', 'pending')
            : 'pending';
        $search = trim((string) $request->query('q', ''));

        $baseQuery = $this->postModerationScope->baseQuery($user, $world);

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
        $moderator = $request->user();
        abort_unless($moderator !== null, 403);

        $statusFilter = (string) $request->validated('status', 'pending');
        $search = trim((string) $request->validated('q', ''));
        $targetStatus = (string) $request->validated('moderation_status');
        $moderationNote = $this->normalizeModerationNote((string) $request->validated('moderation_note', ''));
        $sceneId = (int) $request->validated('scene_id', 0);
        $postIds = collect((array) $request->validated('post_ids', []))
            ->map(static fn ($postId): int => (int) $postId)
            ->filter(static fn (int $postId): bool => $postId > 0)
            ->unique()
            ->values();
        $isHtmxRequest = $request->header('HX-Request') === 'true';

        $result = $this->bulkModeratePostsAction->execute(new BulkModeratePostsInput(
            world: $world,
            moderator: $moderator,
            statusFilter: $statusFilter,
            search: $search,
            targetStatus: $targetStatus,
            moderationNote: $moderationNote,
            sceneId: $sceneId,
            postIds: $postIds,
            isHtmxRequest: $isHtmxRequest,
        ));

        if ($isHtmxRequest && $sceneId > 0) {
            return $this->threadFeedFragment($request, $world, $sceneId);
        }

        return redirect()
            ->route('gm.moderation.index', [
                'world' => $world,
                'status' => $statusFilter,
                'q' => $search !== '' ? $search : null,
            ])
            ->with('status', 'Bulk-Moderation ausgeführt. Betroffene Posts: '.$result->affected.'.');
    }

    public function probe(Request $request, World $world, Post $post): View
    {
        $post->loadMissing(Post::WORLD_CONTEXT_RELATIONS);
        $this->ensurePostBelongsToWorld($world, $post);
        $this->authorize('moderate', $post);

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

    /**
     * @param  Builder<Post>  $query
     */
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

        /** @var Campaign $campaign */
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
