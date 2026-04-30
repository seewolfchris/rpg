<?php

namespace App\Http\Controllers;

use App\Actions\Scene\BuildSceneThreadPageDataAction;
use App\Actions\Scene\BuildSceneShowDataAction;
use App\Actions\Scene\DeleteSceneAction;
use App\Actions\Scene\ResolveSceneJumpRedirectAction;
use App\Actions\Scene\StoreSceneAction;
use App\Actions\Scene\UpdateSceneAction;
use App\Domain\Scene\Exceptions\SceneInventoryQuickActionInvariantViolationException;
use App\Domain\Scene\SceneInventoryQuickActionService;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Scene\StoreSceneInventoryActionRequest;
use App\Http\Requests\Scene\StoreSceneRequest;
use App\Http\Requests\Scene\UpdateSceneRequest;
use App\Models\Campaign;
use App\Models\Scene;
use App\Models\World;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class SceneController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly SceneInventoryQuickActionService $sceneInventoryQuickActionService,
        private readonly BuildSceneShowDataAction $buildSceneShowDataAction,
        private readonly BuildSceneThreadPageDataAction $buildSceneThreadPageDataAction,
        private readonly ResolveSceneJumpRedirectAction $resolveSceneJumpRedirectAction,
        private readonly StoreSceneAction $storeSceneAction,
        private readonly UpdateSceneAction $updateSceneAction,
        private readonly DeleteSceneAction $deleteSceneAction,
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

        $scene = $this->storeSceneAction->execute(
            campaign: $campaign,
            data: $request->validated(),
            creatorId: (int) auth()->id(),
            headerImage: $this->headerImageFromRequest($request),
        );

        return redirect()
            ->route('campaigns.scenes.show', ['world' => $world, 'campaign' => $campaign, 'scene' => $scene])
            ->with('status', 'Szene erstellt.');
    }

    public function show(Request $request, World $world, Campaign $campaign, Scene $scene): View|RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);

        $user = $this->authenticatedUser($request);
        $jump = trim((string) $request->query('jump', ''));

        if ($jump !== '') {
            $jumpUrl = $this->resolveSceneJumpRedirectAction->execute($world, $campaign, $scene, $user, $jump);

            if ($jumpUrl !== null) {
                return redirect()->to($jumpUrl);
            }
        }

        $showData = $this->buildSceneShowDataAction->execute($world, $campaign, $scene, $user);

        return view('scenes.show', [
            'world' => $world,
            'campaign' => $campaign,
            'scene' => $scene,
            'posts' => $showData->posts,
            'pinnedPosts' => $showData->pinnedPosts,
            'pinnedPostJumpUrls' => $showData->pinnedPostJumpUrls,
            'characters' => $showData->characters,
            'probeCharacters' => $showData->probeCharacters,
            'sceneHandouts' => $showData->sceneHandouts,
            'sceneChronicleCount' => $showData->sceneChronicleCount,
            'canModerateScene' => $showData->canModerateScene,
            'subscription' => $showData->subscription,
            'latestPostId' => $showData->latestPostId,
            'unreadPostsCount' => $showData->unreadPostsCount,
            'newPostsSinceLastRead' => $showData->newPostsSinceLastRead,
            'hasUnreadPosts' => $showData->hasUnreadPosts,
            'jumpToLastReadUrl' => $showData->jumpToLastReadUrl,
            'jumpToFirstUnreadUrl' => $showData->jumpToFirstUnreadUrl,
            'jumpToLatestPostUrl' => $showData->jumpToLatestPostUrl,
            'userBookmark' => $showData->userBookmark,
            'bookmarkJumpUrl' => $showData->bookmarkJumpUrl,
        ]);
    }

    public function threadPage(Request $request, World $world, Campaign $campaign, Scene $scene): View
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);

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

        $this->updateSceneAction->execute(
            scene: $scene,
            data: $request->validated(),
            headerImage: $this->headerImageFromRequest($request),
            removeHeaderImage: $request->boolean('remove_header_image'),
        );

        return redirect()
            ->route('campaigns.scenes.show', ['world' => $world, 'campaign' => $campaign, 'scene' => $scene])
            ->with('status', 'Szene aktualisiert.');
    }

    public function destroy(World $world, Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('delete', $scene);
        $this->deleteSceneAction->execute($scene);

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

    private function headerImageFromRequest(Request $request): ?UploadedFile
    {
        $headerImage = $request->file('header_image');

        return $headerImage instanceof UploadedFile ? $headerImage : null;
    }
}
