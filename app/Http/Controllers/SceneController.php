<?php

namespace App\Http\Controllers;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Domain\Scene\Exceptions\SceneInventoryQuickActionInvariantViolationException;
use App\Domain\Scene\SceneInventoryQuickActionService;
use App\Domain\Scene\ScenePostAnchorUrlService;
use App\Domain\Scene\SceneReadTrackingService;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Scene\StoreSceneInventoryActionRequest;
use App\Http\Requests\Scene\StoreSceneRequest;
use App\Http\Requests\Scene\UpdateSceneRequest;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\SceneSubscription;
use App\Models\World;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SceneController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly SceneReadTrackingService $sceneReadTrackingService,
        private readonly ScenePostAnchorUrlService $scenePostAnchorUrlService,
        private readonly SceneInventoryQuickActionService $sceneInventoryQuickActionService,
        private readonly CampaignParticipantResolver $campaignParticipantResolver,
    ) {}

    public function create(World $world, Campaign $campaign): View
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->authorize('create', [Scene::class, $campaign]);

        $previousSceneOptions = $this->previousSceneOptions($campaign);

        return view('scenes.create', compact('world', 'campaign', 'previousSceneOptions'));
    }

    public function store(StoreSceneRequest $request, World $world, Campaign $campaign): RedirectResponse
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->authorize('create', [Scene::class, $campaign]);

        $data = $request->validated();
        unset($data['header_image'], $data['remove_header_image']);
        $data['campaign_id'] = $campaign->id;
        $data['created_by'] = auth()->id();

        if ($request->hasFile('header_image')) {
            $data['header_image_path'] = $request->file('header_image')->store('scene-headers', 'public');
        }

        $scene = Scene::query()->create($data);

        // Scene creator and campaign owner are subscribed by default.
        $this->ensureDefaultSubscriptions($scene, (int) auth()->id(), (int) $campaign->owner_id);

        return redirect()
            ->route('campaigns.scenes.show', ['world' => $world, 'campaign' => $campaign, 'scene' => $scene])
            ->with('status', 'Szene erstellt.');
    }

    public function show(Request $request, World $world, Campaign $campaign, Scene $scene): View|RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);

        $scene->load(['campaign.owner', 'creator', 'previousScene']);
        $scene->loadCount('subscriptions');

        $userId = (int) auth()->id();

        $subscription = SceneSubscription::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $userId)
            ->first();

        $lastReadPostIdBeforeOpen = (int) ($subscription?->last_read_post_id ?? 0);
        $jump = (string) $request->query('jump', '');
        $jumpPostId = match ($jump) {
            'last_read' => $lastReadPostIdBeforeOpen,
            'latest' => $this->latestScenePostId($scene),
            'first_unread' => $subscription ? $this->firstUnreadPostId($scene, $lastReadPostIdBeforeOpen) : 0,
            default => 0,
        };

        if ($jumpPostId > 0) {
            $jumpUrl = $this->scenePostAnchorUrlService->build($world, $campaign, $scene, [$jumpPostId])[$jumpPostId] ?? null;

            if ($jumpUrl !== null) {
                return redirect()->to($jumpUrl);
            }
        }

        $readTracking = $this->sceneReadTrackingService->synchronize(
            scene: $scene,
            subscription: $subscription,
            lastReadPostIdBeforeOpen: $lastReadPostIdBeforeOpen,
        );

        $latestPostId = $readTracking->latestPostId;
        $newPostsSinceLastRead = $readTracking->newPostsSinceLastRead;
        $hasUnreadPosts = $readTracking->hasUnreadPosts;
        $firstUnreadPostId = $readTracking->firstUnreadPostId;
        $unreadPostsCount = 0;

        $posts = $this->threadPostsPaginator($scene);

        $pinnedPosts = Post::query()
            ->where('scene_id', $scene->id)
            ->where('is_pinned', true)
            ->with(['user', 'character', 'pinnedBy'])
            ->orderByDesc('pinned_at')
            ->limit(12)
            ->get();
        $pinnedPostIds = $pinnedPosts
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $characters = auth()->user()
            ->characters()
            ->where('world_id', $campaign->world_id)
            ->orderBy('name')
            ->get();

        $canModerateScene = auth()->user()->isGmOrAdmin() || $scene->campaign->isCoGm(auth()->user());
        $probeCharacters = $canModerateScene
            ? $this->campaignParticipantResolver->probeCharacters($campaign)
            : collect();

        $userBookmark = SceneBookmark::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $userId)
            ->with('post')
            ->first();
        $bookmarkPostId = (int) ($userBookmark?->post_id ?? 0);

        $anchorTargetIds = array_values(array_filter(
            array_merge(
                $pinnedPostIds,
                [$lastReadPostIdBeforeOpen, $firstUnreadPostId, $latestPostId, $bookmarkPostId]
            ),
            static fn (int $postId): bool => $postId > 0
        ));
        $postAnchorUrls = $this->scenePostAnchorUrlService->build($world, $campaign, $scene, $anchorTargetIds);

        $pinnedPostJumpUrls = [];
        foreach ($pinnedPosts as $pinnedPost) {
            $pinnedPostJumpUrls[$pinnedPost->id] = $postAnchorUrls[(int) $pinnedPost->id] ?? null;
        }

        $jumpToLastReadUrl = $lastReadPostIdBeforeOpen > 0
            ? ($postAnchorUrls[$lastReadPostIdBeforeOpen] ?? null)
            : null;

        $jumpToFirstUnreadUrl = $firstUnreadPostId > 0
            ? ($postAnchorUrls[$firstUnreadPostId] ?? null)
            : null;

        $jumpToLatestPostUrl = $latestPostId > 0
            ? ($postAnchorUrls[$latestPostId] ?? null)
            : null;

        $bookmarkJumpUrl = $bookmarkPostId > 0
            ? ($postAnchorUrls[$bookmarkPostId] ?? null)
            : null;

        return view('scenes.show', compact(
            'world',
            'campaign',
            'scene',
            'posts',
            'pinnedPosts',
            'pinnedPostJumpUrls',
            'characters',
            'probeCharacters',
            'canModerateScene',
            'subscription',
            'latestPostId',
            'unreadPostsCount',
            'newPostsSinceLastRead',
            'hasUnreadPosts',
            'jumpToLastReadUrl',
            'jumpToFirstUnreadUrl',
            'jumpToLatestPostUrl',
            'userBookmark',
            'bookmarkJumpUrl',
        ));
    }

    public function threadPage(Request $request, World $world, Campaign $campaign, Scene $scene): View
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);

        $posts = $this->threadPostsPaginator($scene);
        $user = $request->user();
        $subscription = SceneSubscription::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', (int) $user->id)
            ->first();
        $latestPostId = $this->latestScenePostId($scene);
        $unreadPostsCount = $this->sceneUnreadPostsCount($scene, $subscription, $latestPostId);
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

    public function edit(World $world, Campaign $campaign, Scene $scene): View
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('update', $scene);

        $previousSceneOptions = $this->previousSceneOptions($campaign, $scene);

        return view('scenes.edit', compact('world', 'campaign', 'scene', 'previousSceneOptions'));
    }

    public function update(UpdateSceneRequest $request, World $world, Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('update', $scene);

        $data = $request->validated();
        unset($data['header_image'], $data['remove_header_image']);

        if ($request->boolean('remove_header_image') && $scene->header_image_path) {
            Storage::disk('public')->delete($scene->header_image_path);
            $data['header_image_path'] = null;
        }

        if ($request->hasFile('header_image')) {
            if ($scene->header_image_path) {
                Storage::disk('public')->delete($scene->header_image_path);
            }

            $data['header_image_path'] = $request->file('header_image')->store('scene-headers', 'public');
        }

        $scene->update($data);

        return redirect()
            ->route('campaigns.scenes.show', ['world' => $world, 'campaign' => $campaign, 'scene' => $scene])
            ->with('status', 'Szene aktualisiert.');
    }

    public function destroy(World $world, Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('delete', $scene);

        if ($scene->header_image_path) {
            Storage::disk('public')->delete($scene->header_image_path);
        }

        $scene->delete();

        return redirect()
            ->route('campaigns.show', ['world' => $world, 'campaign' => $campaign])
            ->with('status', 'Szene gelöscht.');
    }

    public function inventoryQuickAction(
        StoreSceneInventoryActionRequest $request,
        World $world,
        Campaign $campaign,
        Scene $scene
    ): RedirectResponse {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);

        try {
            $result = $this->sceneInventoryQuickActionService->execute(
                campaign: $campaign,
                scene: $scene,
                actorUserId: (int) auth()->id(),
                data: $request->validated(),
            );
        } catch (SceneInventoryQuickActionInvariantViolationException $exception) {
            report($exception);

            return redirect()
                ->to(route('campaigns.scenes.show', ['world' => $world, 'campaign' => $campaign, 'scene' => $scene]).'#inventory-quick-action')
                ->withInput()
                ->withErrors([
                    $exception->field() => $exception->getMessage(),
                ]);
        }

        if (($result['status'] ?? '') === 'item_not_found') {
            return redirect()
                ->to(route('campaigns.scenes.show', ['world' => $world, 'campaign' => $campaign, 'scene' => $scene]).'#inventory-quick-action')
                ->withInput()
                ->withErrors([
                    'inventory_action_item' => 'Dieser Gegenstand wurde im Inventar des Ziel-Helden nicht gefunden.',
                ]);
        }

        if (($result['status'] ?? '') !== 'ok') {
            return redirect()
                ->to(route('campaigns.scenes.show', ['world' => $world, 'campaign' => $campaign, 'scene' => $scene]).'#inventory-quick-action')
                ->withInput()
                ->withErrors([
                    'inventory_action_character_id' => 'Der Ziel-Held konnte nicht aktualisiert werden.',
                ]);
        }

        return redirect()
            ->to(route('campaigns.scenes.show', ['world' => $world, 'campaign' => $campaign, 'scene' => $scene]).'#inventory-quick-action')
            ->with('status', (string) ($result['message'] ?? 'Inventar-Schnellaktion gespeichert.'));
    }

    private function ensureDefaultSubscriptions(Scene $scene, int $creatorId, int $ownerId): void
    {
        $userIds = collect([$creatorId, $ownerId])
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        foreach ($userIds as $userId) {
            SceneSubscription::query()->firstOrCreate([
                'scene_id' => $scene->id,
                'user_id' => $userId,
            ], [
                'is_muted' => false,
                'last_read_post_id' => null,
                'last_read_at' => now(),
            ]);
        }
    }

    private function threadPostsPaginator(Scene $scene): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Post::query()
            ->where('scene_id', $scene->id)
            ->with(Post::THREAD_PAGE_RELATIONS)
            ->latestByIdHotpath()
            ->paginate(Post::THREAD_POSTS_PER_PAGE)
            ->withQueryString();
    }

    private function latestScenePostId(Scene $scene): int
    {
        return (int) Post::query()
            ->where('scene_id', $scene->id)
            ->max('id');
    }

    private function firstUnreadPostId(Scene $scene, int $lastReadPostId): int
    {
        return (int) Post::query()
            ->where('scene_id', $scene->id)
            ->when(
                $lastReadPostId > 0,
                fn ($query) => $query->where('id', '>', $lastReadPostId),
            )
            ->orderBy('id')
            ->value('id');
    }

    private function sceneUnreadPostsCount(Scene $scene, ?SceneSubscription $subscription, int $latestPostId): int
    {
        if (! $subscription || $latestPostId <= 0 || ! $subscription->hasUnread($latestPostId)) {
            return 0;
        }

        return (int) Post::query()
            ->where('scene_id', $scene->id)
            ->where('id', '>', (int) ($subscription->last_read_post_id ?? 0))
            ->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Scene>
     */
    private function previousSceneOptions(Campaign $campaign, ?Scene $excludeScene = null): \Illuminate\Database\Eloquent\Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Scene> $scenes */
        $scenes = $campaign->scenes()
            ->when($excludeScene instanceof Scene, fn ($query) => $query->whereKeyNot($excludeScene->id))
            ->orderBy('position')
            ->orderBy('created_at')
            ->get(['id', 'title', 'position']);

        return $scenes;
    }
}
