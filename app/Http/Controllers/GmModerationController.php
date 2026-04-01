<?php

namespace App\Http\Controllers;

use App\Actions\Post\ApplyPostModerationFiltersAction;
use App\Actions\Post\BulkModeratePostsAction;
use App\Actions\Post\BulkModeratePostsInput;
use App\Actions\Scene\BuildSceneThreadPageDataAction;
use App\Domain\Post\PostModerationScope;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Post\BulkModerationRequest;
use App\Models\Campaign;
use App\Models\DiceRoll;
use App\Models\Post;
use App\Models\Scene;
use App\Models\World;
use App\Support\ProbeRoller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GmModerationController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly ApplyPostModerationFiltersAction $applyPostModerationFiltersAction,
        private readonly BulkModeratePostsAction $bulkModeratePostsAction,
        private readonly BuildSceneThreadPageDataAction $buildSceneThreadPageDataAction,
        private readonly PostModerationScope $postModerationScope,
        private readonly ProbeRoller $probeRoller,
    ) {}

    public function index(Request $request, World $world): View
    {
        $user = $this->authenticatedUser($request);
        abort_unless($this->postModerationScope->canAccessWorldQueue($user, $world), 403);

        $status = in_array((string) $request->query('status', 'pending'), ['all', 'pending', 'approved', 'rejected'], true)
            ? (string) $request->query('status', 'pending')
            : 'pending';
        $search = trim((string) $request->query('q', ''));

        $baseQuery = $this->postModerationScope->baseQuery($user, $world);

        $postsQuery = (clone $baseQuery)
            ->with(['scene.campaign', 'scene', 'user', 'character', 'approvedBy', 'latestModerationLog.moderator'])
            ->withCount('moderationLogs');

        $this->applyPostModerationFiltersAction->execute($postsQuery, $status, $search);

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
        $moderator = $this->authenticatedUser($request);

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
            'modifier' => ['nullable', 'integer', 'between:-40,40'],
            'target' => ['required', 'integer', 'between:0,100'],
        ]);

        $modifier = (int) ($data['modifier'] ?? 0);
        $target = (int) $data['target'];
        $rolled = $this->probeRoller->roll(DiceRoll::MODE_NORMAL, $modifier);
        $roll = (int) $rolled['kept_roll'];
        $total = (int) $rolled['total'];
        $isSuccess = $total <= $target;

        $outcome = match (true) {
            (bool) $rolled['critical_success'] => 'Kritischer Erfolg',
            (bool) $rolled['critical_failure'] => 'Kritischer Patzer',
            $isSuccess => 'Erfolg',
            default => 'Fehlschlag',
        };

        return view('gm.partials.probe-result', compact('post', 'roll', 'modifier', 'total', 'target', 'outcome'));
    }

    private function normalizeModerationNote(string $note): ?string
    {
        $normalized = trim($note);

        return $normalized !== '' ? $normalized : null;
    }

    private function threadFeedFragment(Request $request, World $world, int $sceneId): View
    {
        $user = $this->authenticatedUser($request);
        $scene = Scene::query()
            ->with('campaign')
            ->findOrFail($sceneId);

        /** @var Campaign $campaign */
        $campaign = $scene->campaign;
        abort_unless((int) $campaign->world_id === (int) $world->id, 404);
        $this->authorize('view', $scene);
        $threadData = $this->buildSceneThreadPageDataAction->execute($scene, $campaign, $user);

        return view('scenes.partials.thread-page', [
            'posts' => $threadData->posts,
            'campaign' => $campaign,
            'scene' => $scene,
            'subscription' => $threadData->subscription,
            'latestPostId' => $threadData->latestPostId,
            'unreadPostsCount' => $threadData->unreadPostsCount,
            'canModerateScene' => $threadData->canModerateScene,
        ]);
    }
}
