<?php

namespace App\Http\Controllers;

use App\Http\Requests\Scene\StoreSceneRequest;
use App\Http\Requests\Scene\StoreSceneInventoryActionRequest;
use App\Http\Requests\Scene\UpdateSceneRequest;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\SceneSubscription;
use App\Support\CharacterInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SceneController extends Controller
{
    private const THREAD_POSTS_PER_PAGE = 20;

    public function __construct(
        private readonly CharacterInventoryService $inventoryService,
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
            $jumpUrl = $this->buildPostAnchorUrl($campaign, $scene, $lastReadPostIdBeforeOpen);

            if ($jumpUrl !== null) {
                return redirect()->to($jumpUrl);
            }
        }

        $latestPostId = (int) Post::query()
            ->where('scene_id', $scene->id)
            ->max('id');

        $newPostsSinceLastRead = 0;
        $hasUnreadPosts = false;
        $firstUnreadPostId = 0;

        if ($subscription) {
            $hasUnreadPosts = $subscription->hasUnread($latestPostId);

            if ($hasUnreadPosts) {
                $newPostsSinceLastRead = $lastReadPostIdBeforeOpen > 0
                    ? Post::query()
                        ->where('scene_id', $scene->id)
                        ->where('id', '>', $lastReadPostIdBeforeOpen)
                        ->count()
                    : Post::query()
                        ->where('scene_id', $scene->id)
                        ->count();

                $firstUnreadPostId = (int) Post::query()
                    ->where('scene_id', $scene->id)
                    ->when(
                        $lastReadPostIdBeforeOpen > 0,
                        fn ($query) => $query->where('id', '>', $lastReadPostIdBeforeOpen),
                    )
                    ->orderBy('id')
                    ->value('id');

                $subscription->markRead($latestPostId);
                $subscription->refresh();
                $hasUnreadPosts = false;
            }
        }

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

        $pinnedPostJumpUrls = [];
        foreach ($pinnedPosts as $pinnedPost) {
            $pinnedPostJumpUrls[$pinnedPost->id] = $this->buildPostAnchorUrl($campaign, $scene, (int) $pinnedPost->id);
        }

        $characters = auth()->user()
            ->characters()
            ->orderBy('name')
            ->get();

        $canModerateScene = auth()->user()->isGmOrAdmin() || $scene->campaign->isCoGm(auth()->user());
        $probeCharacters = $canModerateScene
            ? $this->resolveProbeCharacters($campaign)
            : collect();

        $userBookmark = SceneBookmark::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $userId)
            ->with('post')
            ->first();

        $jumpToLastReadUrl = $lastReadPostIdBeforeOpen > 0
            ? $this->buildPostAnchorUrl($campaign, $scene, $lastReadPostIdBeforeOpen)
            : null;

        $jumpToFirstUnreadUrl = $firstUnreadPostId > 0
            ? $this->buildPostAnchorUrl($campaign, $scene, $firstUnreadPostId)
            : null;

        $jumpToLatestPostUrl = $latestPostId > 0
            ? $this->buildPostAnchorUrl($campaign, $scene, $latestPostId)
            : null;

        $bookmarkJumpUrl = $userBookmark?->post_id
            ? $this->buildPostAnchorUrl($campaign, $scene, (int) $userBookmark->post_id)
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
            ->with('status', 'Szene geloescht.');
    }

    public function inventoryQuickAction(
        StoreSceneInventoryActionRequest $request,
        Campaign $campaign,
        Scene $scene
    ): RedirectResponse {
        $this->ensureSceneBelongsToCampaign($campaign, $scene);
        $this->authorize('view', $scene);

        $data = $request->validated();
        $characterId = (int) $data['inventory_action_character_id'];
        $actionType = (string) $data['inventory_action_type'];
        $item = trim((string) $data['inventory_action_item']);
        $quantity = max(1, min(999, (int) ($data['inventory_action_quantity'] ?? 1)));
        $equipped = (bool) ($data['inventory_action_equipped'] ?? false);
        $note = trim((string) ($data['inventory_action_note'] ?? ''));

        $result = DB::transaction(function () use ($characterId, $actionType, $item, $quantity, $equipped, $note, $campaign, $scene): array {
            $character = Character::query()
                ->lockForUpdate()
                ->find($characterId);

            if (! $character) {
                return ['status' => 'missing_character'];
            }

            $beforeInventory = $this->inventoryService->normalize($character->inventory ?? []);
            $afterInventory = $beforeInventory;
            $removedQuantity = 0;
            $removedEquipped = null;

            if ($actionType === 'remove') {
                $removeResult = $this->inventoryService->remove($beforeInventory, $item, $quantity);
                $afterInventory = $removeResult['inventory'];
                $removedQuantity = (int) $removeResult['removed'];
                $removedEquipped = $removeResult['removed_equipped'];

                if ($removedQuantity <= 0) {
                    return [
                        'status' => 'item_not_found',
                        'character_name' => (string) $character->name,
                    ];
                }
            } else {
                $afterInventory = $this->inventoryService->add(
                    inventory: $beforeInventory,
                    name: $item,
                    quantity: $quantity,
                    equipped: $equipped,
                );
            }

            $character->inventory = $afterInventory;
            $character->save();

            if ($actionType === 'remove') {
                $operations = $this->inventoryService->diff($beforeInventory, $afterInventory);
                if ($operations === []) {
                    $operations = [[
                        'action' => 'remove',
                        'item_name' => $item,
                        'quantity' => $removedQuantity,
                        'equipped' => (bool) ($removedEquipped ?? false),
                    ]];
                }
            } else {
                $operations = $this->inventoryService->diff($beforeInventory, $afterInventory);
            }

            $this->inventoryService->log(
                character: $character,
                actorUserId: (int) auth()->id(),
                source: 'scene_inventory_quick_action',
                operations: $operations,
                note: $note !== '' ? $note : null,
                context: [
                    'campaign_id' => $campaign->id,
                    'scene_id' => $scene->id,
                ],
            );

            return [
                'status' => 'ok',
                'character_name' => (string) $character->name,
                'quantity' => $actionType === 'remove' ? $removedQuantity : $quantity,
                'equipped' => $actionType === 'remove' ? (bool) ($removedEquipped ?? false) : $equipped,
            ];
        });

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

        $statusLabel = $actionType === 'remove' ? 'entfernt' : 'hinzugefuegt';
        $displayQuantity = max(1, (int) ($result['quantity'] ?? $quantity));
        $equippedLabel = (bool) ($result['equipped'] ?? false) ? ' (ausgeruestet)' : '';
        $statusMessage = 'Inventar-Schnellaktion: '.$displayQuantity.'x '.$item.$equippedLabel.' bei '.$result['character_name'].' '.$statusLabel.'.';

        if ($note !== '') {
            $statusMessage .= ' Notiz: '.$note;
        }

        return redirect()
            ->to(route('campaigns.scenes.show', [$campaign, $scene]).'#inventory-quick-action')
            ->with('status', $statusMessage);
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

    private function buildPostAnchorUrl(Campaign $campaign, Scene $scene, int $postId): ?string
    {
        $targetPost = Post::query()
            ->where('scene_id', $scene->id)
            ->whereKey($postId)
            ->first(['id']);

        if (! $targetPost) {
            return null;
        }

        $newerPostsCount = (int) Post::query()
            ->where('scene_id', $scene->id)
            ->where('id', '>', $targetPost->id)
            ->count();

        $page = intdiv($newerPostsCount, self::THREAD_POSTS_PER_PAGE) + 1;

        return route('campaigns.scenes.show', [
            'campaign' => $campaign,
            'scene' => $scene,
            'page' => $page,
        ]).'#post-'.$targetPost->id;
    }

    /**
     * @return Collection<int, Character>
     */
    private function resolveProbeCharacters(Campaign $campaign): Collection
    {
        $participantUserIds = $campaign->invitations()
            ->where('status', CampaignInvitation::STATUS_ACCEPTED)
            ->pluck('user_id');

        $participantUserIds = $participantUserIds
            ->merge([(int) $campaign->owner_id])
            ->unique()
            ->values();

        if ($participantUserIds->isEmpty()) {
            return collect();
        }

        return Character::query()
            ->whereIn('user_id', $participantUserIds)
            ->with('user:id,name')
            ->orderBy('name')
            ->get(['id', 'user_id', 'name']);
    }
}
