<?php

namespace App\Http\Controllers;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Domain\Scene\SceneInventoryQuickActionService;
use App\Domain\Scene\ScenePostAnchorUrlService;
use App\Domain\Scene\SceneReadTrackingService;
use App\Http\Requests\Scene\StoreSceneInventoryActionRequest;
use App\Http\Requests\Scene\StoreSceneRequest;
use App\Http\Requests\Scene\UpdateSceneRequest;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\SceneSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SceneController extends Controller
{
    private const THREAD_POSTS_PER_PAGE = 20;

    public function __construct(
        private readonly SceneReadTrackingService $sceneReadTrackingService,
        private readonly ScenePostAnchorUrlService $scenePostAnchorUrlService,
        private readonly SceneInventoryQuickActionService $sceneInventoryQuickActionService,
        private readonly CampaignParticipantResolver $campaignParticipantResolver,
    ) {}

    public function create(Campaign $campaign): View
    {
        $this->authorize('create', [Scene::class, $campaign]);

        return view('scenes.create', compact('campaign'));
    }

    public function store(StoreSceneRequest $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('create', [Scene::class, $campaign]);

        $data = $request->validated();
        $data['campaign_id'] = $campaign->id;
        $data['created_by'] = auth()->id();

        $scene = Scene::query()->create($data);

        // Scene creator and campaign owner are subscribed by default.
        $this->ensureDefaultSubscriptions($scene, (int) auth()->id(), (int) $campaign->owner_id);

        return redirect()
            ->route('campaigns.scenes.show', [$campaign, $scene])
            ->with('status', 'Szene erstellt.');
    }

    public function show(Request $request, Campaign $campaign, Scene $scene): View|RedirectResponse
    {
        $this->ensureSceneBelongsToCampaign($campaign, $scene);
        $this->authorize('view', $scene);

        $scene->load(['campaign.owner', 'creator']);
        $scene->loadCount('subscriptions');

        $userId = (int) auth()->id();

        $subscription = SceneSubscription::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $userId)
            ->first();

        $lastReadPostIdBeforeOpen = (int) ($subscription?->last_read_post_id ?? 0);

        if ($request->query('jump') === 'last_read' && $lastReadPostIdBeforeOpen > 0) {
            $jumpUrl = $this->scenePostAnchorUrlService->build($campaign, $scene, [$lastReadPostIdBeforeOpen])[$lastReadPostIdBeforeOpen] ?? null;

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

        $posts = Post::query()
            ->where('scene_id', $scene->id)
            ->with(['user', 'character', 'approvedBy', 'pinnedBy', 'revisions.editor', 'moderationLogs.moderator', 'diceRoll.character.user'])
            ->latest()
            ->paginate(self::THREAD_POSTS_PER_PAGE)
            ->withQueryString();

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
        $postAnchorUrls = $this->scenePostAnchorUrlService->build($campaign, $scene, $anchorTargetIds);

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
            'newPostsSinceLastRead',
            'hasUnreadPosts',
            'jumpToLastReadUrl',
            'jumpToFirstUnreadUrl',
            'jumpToLatestPostUrl',
            'userBookmark',
            'bookmarkJumpUrl',
        ));
    }

    public function edit(Campaign $campaign, Scene $scene): View
    {
        $this->ensureSceneBelongsToCampaign($campaign, $scene);
        $this->authorize('update', $scene);

        return view('scenes.edit', compact('campaign', 'scene'));
    }

    public function update(UpdateSceneRequest $request, Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToCampaign($campaign, $scene);
        $this->authorize('update', $scene);

        $scene->update($request->validated());

        return redirect()
            ->route('campaigns.scenes.show', [$campaign, $scene])
            ->with('status', 'Szene aktualisiert.');
    }

    public function destroy(Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToCampaign($campaign, $scene);
        $this->authorize('delete', $scene);

        $scene->delete();

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('status', 'Szene gelöscht.');
    }

    public function inventoryQuickAction(
        StoreSceneInventoryActionRequest $request,
        Campaign $campaign,
        Scene $scene
    ): RedirectResponse {
        $this->ensureSceneBelongsToCampaign($campaign, $scene);
        $this->authorize('view', $scene);

        $result = $this->sceneInventoryQuickActionService->execute(
            campaign: $campaign,
            scene: $scene,
            actorUserId: (int) auth()->id(),
            data: $request->validated(),
        );

        if (($result['status'] ?? '') === 'item_not_found') {
            return redirect()
                ->to(route('campaigns.scenes.show', [$campaign, $scene]).'#inventory-quick-action')
                ->withInput()
                ->withErrors([
                    'inventory_action_item' => 'Dieser Gegenstand wurde im Inventar des Ziel-Helden nicht gefunden.',
                ]);
        }

        if (($result['status'] ?? '') !== 'ok') {
            return redirect()
                ->to(route('campaigns.scenes.show', [$campaign, $scene]).'#inventory-quick-action')
                ->withInput()
                ->withErrors([
                    'inventory_action_character_id' => 'Der Ziel-Held konnte nicht aktualisiert werden.',
                ]);
        }

        return redirect()
            ->to(route('campaigns.scenes.show', [$campaign, $scene]).'#inventory-quick-action')
            ->with('status', (string) ($result['message'] ?? 'Inventar-Schnellaktion gespeichert.'));
    }

    private function ensureSceneBelongsToCampaign(Campaign $campaign, Scene $scene): void
    {
        abort_unless($scene->campaign_id === $campaign->id, 404);
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
}
