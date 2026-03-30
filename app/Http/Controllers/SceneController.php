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
use App\Models\User;
use App\Models\World;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

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
        $data['header_image_path'] = null;
        $stagedHeaderImage = $this->stageHeaderImageUpload($request);

        try {
            $scene = DB::transaction(function () use ($campaign, $data, $stagedHeaderImage): Scene {
                $scene = Scene::query()->create($data);

                // Scene creator and campaign owner are subscribed by default.
                $this->ensureDefaultSubscriptions($scene, (int) auth()->id(), (int) $campaign->owner_id);

                if ($stagedHeaderImage !== null) {
                    DB::afterCommit(function () use ($scene, $stagedHeaderImage): void {
                        $this->finalizeHeaderImageReplacement($scene, $stagedHeaderImage, null);
                    });
                }

                return $scene;
            });
        } catch (Throwable $exception) {
            $this->discardStagedHeaderImage($stagedHeaderImage);

            throw $exception;
        }

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

        $lastReadPostIdBeforeOpen = $subscription instanceof SceneSubscription
            ? (int) $subscription->last_read_post_id
            : 0;
        $authenticatedUser = auth()->user();

        if (! $authenticatedUser instanceof User) {
            abort(403);
        }

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

        $characters = $authenticatedUser
            ->characters()
            ->where('world_id', $campaign->world_id)
            ->orderBy('name')
            ->get();

        $canModerateScene = $this->canModerateScene($authenticatedUser, $campaign);
        $probeCharacters = $canModerateScene
            ? $this->campaignParticipantResolver->probeCharacters($campaign)
            : collect();

        $userBookmark = SceneBookmark::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $userId)
            ->with('post')
            ->first();
        $bookmarkPostId = $userBookmark instanceof SceneBookmark
            ? (int) $userBookmark->post_id
            : 0;

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
        $user = $this->authenticatedUser($request);
        $subscription = SceneSubscription::query()
            ->where('scene_id', $scene->id)
            ->where('user_id', $user->id)
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
        $stagedHeaderImage = $this->stageHeaderImageUpload($request);
        $replaceHeaderImage = $stagedHeaderImage !== null;
        $removeHeaderImage = $request->boolean('remove_header_image');
        $previousHeaderPath = is_string($scene->header_image_path) && $scene->header_image_path !== ''
            ? $scene->header_image_path
            : null;

        try {
            DB::transaction(function () use (
                $scene,
                $data,
                $replaceHeaderImage,
                $removeHeaderImage,
                $previousHeaderPath,
                $stagedHeaderImage
            ): void {
                if ($removeHeaderImage && ! $replaceHeaderImage) {
                    $data['header_image_path'] = null;
                }

                $scene->update($data);

                if ($replaceHeaderImage && $stagedHeaderImage !== null) {
                    DB::afterCommit(function () use ($scene, $stagedHeaderImage, $previousHeaderPath): void {
                        $this->finalizeHeaderImageReplacement($scene, $stagedHeaderImage, $previousHeaderPath);
                    });

                    return;
                }

                if ($removeHeaderImage && $previousHeaderPath !== null) {
                    DB::afterCommit(function () use ($previousHeaderPath): void {
                        $this->deletePublicFile($previousHeaderPath);
                    });
                }
            });
        } catch (Throwable $exception) {
            $this->discardStagedHeaderImage($stagedHeaderImage);

            throw $exception;
        }

        return redirect()
            ->route('campaigns.scenes.show', ['world' => $world, 'campaign' => $campaign, 'scene' => $scene])
            ->with('status', 'Szene aktualisiert.');
    }

    public function destroy(World $world, Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('delete', $scene);
        $headerImagePath = is_string($scene->header_image_path) && $scene->header_image_path !== ''
            ? $scene->header_image_path
            : null;

        DB::transaction(function () use ($scene, $headerImagePath): void {
            $scene->delete();

            if ($headerImagePath !== null) {
                DB::afterCommit(function () use ($headerImagePath): void {
                    $this->deletePublicFile($headerImagePath);
                });
            }
        });

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

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Post>
     */
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

    private function canModerateScene(User $user, Campaign $campaign): bool
    {
        return $user->isGmOrAdmin() || $campaign->isCoGm($user);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Scene>
     */
    private function previousSceneOptions(Campaign $campaign, ?Scene $excludeScene = null): \Illuminate\Database\Eloquent\Collection
    {
        $excludeSceneId = $excludeScene instanceof Scene ? $excludeScene->id : null;

        /** @var \Illuminate\Database\Eloquent\Collection<int, Scene> $scenes */
        $scenes = $campaign->scenes()
            ->when($excludeSceneId !== null, fn ($query) => $query->whereKeyNot($excludeSceneId))
            ->orderBy('position')
            ->orderBy('created_at')
            ->get(['id', 'title', 'position']);

        return $scenes;
    }

    /**
     * @return array{disk: string, staged_path: string, extension: string}|null
     */
    private function stageHeaderImageUpload(Request $request): ?array
    {
        if (! $request->hasFile('header_image')) {
            return null;
        }

        $file = $request->file('header_image');
        if ($file === null) {
            return null;
        }

        $stagedPath = $file->store('scene-headers/staged', 'public');
        if (! is_string($stagedPath) || trim($stagedPath) === '') {
            throw new \RuntimeException('Headerbild konnte nicht zwischengespeichert werden.');
        }

        $extension = strtolower((string) $file->extension());

        return [
            'disk' => 'public',
            'staged_path' => $stagedPath,
            'extension' => $extension !== '' ? $extension : 'jpg',
        ];
    }

    /**
     * @param  array{disk: string, staged_path: string, extension: string}|null  $stagedHeaderImage
     */
    private function discardStagedHeaderImage(?array $stagedHeaderImage): void
    {
        if ($stagedHeaderImage === null) {
            return;
        }

        $disk = Storage::disk($stagedHeaderImage['disk']);
        $stagedPath = $stagedHeaderImage['staged_path'];

        if ($disk->exists($stagedPath)) {
            $disk->delete($stagedPath);
        }
    }

    /**
     * @param  array{disk: string, staged_path: string, extension: string}  $stagedHeaderImage
     */
    private function finalizeHeaderImageReplacement(Scene $scene, array $stagedHeaderImage, ?string $previousHeaderPath): void
    {
        $disk = Storage::disk($stagedHeaderImage['disk']);
        $stagedPath = $stagedHeaderImage['staged_path'];
        $finalPath = 'scene-headers/'.$scene->id.'-'.Str::uuid().'.'.$stagedHeaderImage['extension'];

        try {
            if (! $disk->exists($stagedPath)) {
                throw new \RuntimeException('Zwischengespeichertes Headerbild fehlt bei Finalisierung.');
            }

            if (! $disk->move($stagedPath, $finalPath)) {
                throw new \RuntimeException('Zwischengespeichertes Headerbild konnte nicht finalisiert werden.');
            }

            $updated = $scene->newQuery()
                ->whereKey($scene->getKey())
                ->update(['header_image_path' => $finalPath]);

            if ($updated !== 1) {
                throw new \RuntimeException('Headerbild-Pfad konnte nach Finalisierung nicht persistiert werden.');
            }

            $scene->header_image_path = $finalPath;

            if (
                is_string($previousHeaderPath)
                && $previousHeaderPath !== ''
                && $previousHeaderPath !== $finalPath
            ) {
                $this->deletePublicFile($previousHeaderPath);
            }
        } catch (Throwable $exception) {
            if ($disk->exists($stagedPath)) {
                $disk->delete($stagedPath);
            }

            if ($disk->exists($finalPath)) {
                $disk->delete($finalPath);
            }

            report($exception);
        }
    }

    private function deletePublicFile(string $path): void
    {
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
