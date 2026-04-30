<?php

namespace App\Http\Controllers;

use App\Actions\StoryLog\DeleteStoryLogEntryAction;
use App\Actions\StoryLog\RevealStoryLogEntryAction;
use App\Actions\StoryLog\StoreStoryLogEntryAction;
use App\Actions\StoryLog\UnrevealStoryLogEntryAction;
use App\Actions\StoryLog\UpdateStoryLogEntryAction;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\StoryLog\StoreStoryLogEntryRequest;
use App\Http\Requests\StoryLog\UpdateStoryLogEntryRequest;
use App\Models\Campaign;
use App\Models\Scene;
use App\Models\StoryLogEntry;
use App\Models\World;
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

    public function create(World $world, Campaign $campaign): View
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->authorize('create', [StoryLogEntry::class, $campaign]);

        $storyLogEntry = new StoryLogEntry;
        $sceneOptions = $this->sceneOptionsForCampaign($campaign);

        return view('story-log.create', compact('world', 'campaign', 'storyLogEntry', 'sceneOptions'));
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

        return redirect()
            ->route('campaigns.story-log.show', [
                'world' => $world,
                'campaign' => $campaign,
                'storyLogEntry' => $storyLogEntry,
            ])
            ->with('status', 'Chronik-Eintrag erstellt.');
    }

    public function show(World $world, Campaign $campaign, StoryLogEntry $storyLogEntry): View
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureStoryLogEntryBelongsToCampaign($campaign, $storyLogEntry);
        $this->authorize('view', $storyLogEntry);

        $storyLogEntry->loadMissing(['scene', 'createdBy', 'updatedBy']);

        return view('story-log.show', compact('world', 'campaign', 'storyLogEntry'));
    }

    public function edit(World $world, Campaign $campaign, StoryLogEntry $storyLogEntry): View
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureStoryLogEntryBelongsToCampaign($campaign, $storyLogEntry);
        $this->authorize('update', $storyLogEntry);

        $storyLogEntry->loadMissing(['scene', 'createdBy', 'updatedBy']);
        $sceneOptions = $this->sceneOptionsForCampaign($campaign);

        return view('story-log.edit', compact('world', 'campaign', 'storyLogEntry', 'sceneOptions'));
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

        return redirect()
            ->route('campaigns.story-log.show', [
                'world' => $world,
                'campaign' => $campaign,
                'storyLogEntry' => $storyLogEntry,
            ])
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

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Scene>
     */
    private function sceneOptionsForCampaign(Campaign $campaign): \Illuminate\Database\Eloquent\Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Scene> $scenes */
        $scenes = $campaign->scenes()
            ->orderBy('position')
            ->orderBy('title')
            ->get(['id', 'title', 'position']);

        return $scenes;
    }
}
