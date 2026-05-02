<?php

namespace App\Http\Controllers;

use App\Actions\StoryLog\DeleteStoryLogEntryAction;
use App\Actions\StoryLog\RevealStoryLogEntryAction;
use App\Actions\StoryLog\StoreStoryLogEntryAction;
use App\Actions\StoryLog\UnrevealStoryLogEntryAction;
use App\Actions\StoryLog\UpdateStoryLogEntryAction;
use App\Domain\Campaign\CampaignSceneOptionsProvider;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\StoryLog\StoreStoryLogEntryRequest;
use App\Http\Requests\StoryLog\UpdateStoryLogEntryRequest;
use App\Models\Campaign;
use App\Models\StoryLogEntry;
use App\Models\World;
use App\Support\Navigation\SafeReturnUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoryLogEntryController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly StoreStoryLogEntryAction $storeStoryLogEntryAction,
        private readonly UpdateStoryLogEntryAction $updateStoryLogEntryAction,
        private readonly DeleteStoryLogEntryAction $deleteStoryLogEntryAction,
        private readonly RevealStoryLogEntryAction $revealStoryLogEntryAction,
        private readonly UnrevealStoryLogEntryAction $unrevealStoryLogEntryAction,
        private readonly CampaignSceneOptionsProvider $campaignSceneOptionsProvider,
        private readonly SafeReturnUrl $safeReturnUrl,
    ) {}

    public function index(Request $request, World $world, Campaign $campaign): View
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->authorize('viewAny', [StoryLogEntry::class, $campaign]);

        $user = $this->authenticatedUser($request);
        $canManage = $campaign->canManageCampaign($user);

        $storyLogEntries = StoryLogEntry::query()
            ->where('campaign_id', (int) $campaign->id)
            ->with(['scene', 'createdBy', 'updatedBy'])
            ->when(
                ! $canManage,
                fn ($query) => $query->whereNotNull('revealed_at')
            )
            ->orderByRaw('sort_order IS NULL')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(24)
            ->withQueryString();

        return view('story-log.index', compact('world', 'campaign', 'storyLogEntries', 'canManage'));
    }

    public function create(Request $request, World $world, Campaign $campaign): View
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->authorize('create', [StoryLogEntry::class, $campaign]);

        $storyLogEntry = new StoryLogEntry;
        $sceneOptions = $this->campaignSceneOptionsProvider->forCampaign($campaign);
        $fallback = route('campaigns.story-log.index', ['world' => $world, 'campaign' => $campaign]);
        $backUrl = $this->safeReturnUrl->resolve($request, $fallback);
        $returnTo = $this->safeReturnUrl->carry($request);

        return view('story-log.create', compact('world', 'campaign', 'storyLogEntry', 'sceneOptions', 'backUrl', 'returnTo'));
    }

    public function store(
        StoreStoryLogEntryRequest $request,
        World $world,
        Campaign $campaign,
    ): RedirectResponse {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->authorize('create', [StoryLogEntry::class, $campaign]);

        $actor = $this->authenticatedUser($request);
        $data = $request->validated();

        $storyLogEntry = $this->storeStoryLogEntryAction->execute($campaign, $actor, $data);

        $parameters = [
            'world' => $world,
            'campaign' => $campaign,
            'storyLogEntry' => $storyLogEntry,
        ];
        $returnTo = $this->safeReturnUrl->carry($request);
        if (is_string($returnTo) && $returnTo !== '') {
            $parameters['return_to'] = $returnTo;
        }

        return redirect()
            ->route('campaigns.story-log.show', $parameters)
            ->with('status', 'Chronik-Eintrag erstellt.');
    }

    public function show(Request $request, World $world, Campaign $campaign, StoryLogEntry $storyLogEntry): View
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureStoryLogEntryBelongsToCampaign($campaign, $storyLogEntry);
        $this->authorize('view', $storyLogEntry);

        $storyLogEntry->loadMissing(['scene', 'createdBy', 'updatedBy']);
        $fallback = $this->storyLogFallbackUrl($world, $campaign, $storyLogEntry);
        $backUrl = $this->safeReturnUrl->resolve($request, $fallback);
        $returnTo = $this->safeReturnUrl->carry($request);

        return view('story-log.show', compact('world', 'campaign', 'storyLogEntry', 'backUrl', 'returnTo'));
    }

    public function edit(Request $request, World $world, Campaign $campaign, StoryLogEntry $storyLogEntry): View
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureStoryLogEntryBelongsToCampaign($campaign, $storyLogEntry);
        $this->authorize('update', $storyLogEntry);

        $storyLogEntry->loadMissing(['scene', 'createdBy', 'updatedBy']);
        $sceneOptions = $this->campaignSceneOptionsProvider->forCampaign($campaign);
        $fallback = $this->storyLogFallbackUrl($world, $campaign, $storyLogEntry);
        $backUrl = $this->safeReturnUrl->resolve($request, $fallback);
        $returnTo = $this->safeReturnUrl->carry($request);

        return view('story-log.edit', compact('world', 'campaign', 'storyLogEntry', 'sceneOptions', 'backUrl', 'returnTo'));
    }

    public function update(
        UpdateStoryLogEntryRequest $request,
        World $world,
        Campaign $campaign,
        StoryLogEntry $storyLogEntry
    ): RedirectResponse {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureStoryLogEntryBelongsToCampaign($campaign, $storyLogEntry);
        $this->authorize('update', $storyLogEntry);

        $actor = $this->authenticatedUser($request);
        $data = $request->validated();

        $this->updateStoryLogEntryAction->execute($storyLogEntry, $actor, $data);

        $parameters = [
            'world' => $world,
            'campaign' => $campaign,
            'storyLogEntry' => $storyLogEntry,
        ];
        $returnTo = $this->safeReturnUrl->carry($request);
        if (is_string($returnTo) && $returnTo !== '') {
            $parameters['return_to'] = $returnTo;
        }

        return redirect()
            ->route('campaigns.story-log.show', $parameters)
            ->with('status', 'Chronik-Eintrag aktualisiert.');
    }

    public function destroy(World $world, Campaign $campaign, StoryLogEntry $storyLogEntry): RedirectResponse
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureStoryLogEntryBelongsToCampaign($campaign, $storyLogEntry);
        $this->authorize('delete', $storyLogEntry);

        $this->deleteStoryLogEntryAction->execute($storyLogEntry);

        return redirect()
            ->route('campaigns.story-log.index', [
                'world' => $world,
                'campaign' => $campaign,
            ])
            ->with('status', 'Chronik-Eintrag gelöscht.');
    }

    public function reveal(Request $request, World $world, Campaign $campaign, StoryLogEntry $storyLogEntry): RedirectResponse
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureStoryLogEntryBelongsToCampaign($campaign, $storyLogEntry);
        $this->authorize('reveal', $storyLogEntry);

        $actor = $this->authenticatedUser($request);
        $this->revealStoryLogEntryAction->execute($storyLogEntry, $actor);

        return redirect()
            ->route('campaigns.story-log.show', [
                'world' => $world,
                'campaign' => $campaign,
                'storyLogEntry' => $storyLogEntry,
            ])
            ->with('status', 'Chronik-Eintrag freigegeben.');
    }

    public function unreveal(Request $request, World $world, Campaign $campaign, StoryLogEntry $storyLogEntry): RedirectResponse
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureStoryLogEntryBelongsToCampaign($campaign, $storyLogEntry);
        $this->authorize('unreveal', $storyLogEntry);

        $actor = $this->authenticatedUser($request);
        $this->unrevealStoryLogEntryAction->execute($storyLogEntry, $actor);

        return redirect()
            ->route('campaigns.story-log.show', [
                'world' => $world,
                'campaign' => $campaign,
                'storyLogEntry' => $storyLogEntry,
            ])
            ->with('status', 'Chronik-Eintrag verborgen.');
    }

    private function storyLogFallbackUrl(World $world, Campaign $campaign, StoryLogEntry $storyLogEntry): string
    {
        $scene = $storyLogEntry->scene;
        if ($scene !== null && (int) $scene->campaign_id === (int) $campaign->id) {
            return route('campaigns.scenes.show', [
                'world' => $world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]);
        }

        return route('campaigns.story-log.index', [
            'world' => $world,
            'campaign' => $campaign,
        ]);
    }

}
