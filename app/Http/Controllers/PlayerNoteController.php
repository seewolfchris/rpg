<?php

namespace App\Http\Controllers;

use App\Actions\PlayerNote\DeletePlayerNoteAction;
use App\Actions\PlayerNote\StorePlayerNoteAction;
use App\Actions\PlayerNote\UpdatePlayerNoteAction;
use App\Domain\Campaign\CampaignSceneOptionsProvider;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\PlayerNote\StorePlayerNoteRequest;
use App\Http\Requests\PlayerNote\UpdatePlayerNoteRequest;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\PlayerNote;
use App\Models\World;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlayerNoteController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly StorePlayerNoteAction $storePlayerNoteAction,
        private readonly UpdatePlayerNoteAction $updatePlayerNoteAction,
        private readonly DeletePlayerNoteAction $deletePlayerNoteAction,
        private readonly CampaignSceneOptionsProvider $campaignSceneOptionsProvider,
    ) {}

    public function index(Request $request, World $world, Campaign $campaign): View
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->authorize('viewAny', [PlayerNote::class, $campaign]);

        $actor = $this->authenticatedUser($request);

        $playerNotes = PlayerNote::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where('user_id', (int) $actor->id)
            ->with(['scene', 'character'])
            ->orderByRaw('sort_order IS NULL')
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(24)
            ->withQueryString();

        return view('player-notes.index', compact('world', 'campaign', 'playerNotes'));
    }

    public function create(Request $request, World $world, Campaign $campaign): View
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->authorize('create', [PlayerNote::class, $campaign]);

        $actor = $this->authenticatedUser($request);
        $playerNote = new PlayerNote;
        $sceneOptions = $this->campaignSceneOptionsProvider->forCampaign($campaign);
        $characterOptions = $this->characterOptionsForCampaignAndUser($campaign, $actor);

        return view('player-notes.create', compact('world', 'campaign', 'playerNote', 'sceneOptions', 'characterOptions'));
    }

    public function store(
        StorePlayerNoteRequest $request,
        World $world,
        Campaign $campaign,
    ): RedirectResponse {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->authorize('create', [PlayerNote::class, $campaign]);

        $actor = $this->authenticatedUser($request);
        $data = $request->validated();
        $playerNote = $this->storePlayerNoteAction->execute($campaign, $actor, $data);

        return redirect()
            ->route('campaigns.player-notes.show', [
                'world' => $world,
                'campaign' => $campaign,
                'playerNote' => $playerNote,
            ])
            ->with('status', 'Notiz erstellt.');
    }

    public function show(World $world, Campaign $campaign, PlayerNote $playerNote): View
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensurePlayerNoteBelongsToCampaign($campaign, $playerNote);
        $this->authorize('view', $playerNote);

        $playerNote->loadMissing(['scene', 'character']);

        return view('player-notes.show', compact('world', 'campaign', 'playerNote'));
    }

    public function edit(Request $request, World $world, Campaign $campaign, PlayerNote $playerNote): View
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensurePlayerNoteBelongsToCampaign($campaign, $playerNote);
        $this->authorize('update', $playerNote);

        $actor = $this->authenticatedUser($request);
        $playerNote->loadMissing(['scene', 'character']);
        $sceneOptions = $this->campaignSceneOptionsProvider->forCampaign($campaign);
        $characterOptions = $this->characterOptionsForCampaignAndUser($campaign, $actor);

        return view('player-notes.edit', compact('world', 'campaign', 'playerNote', 'sceneOptions', 'characterOptions'));
    }

    public function update(
        UpdatePlayerNoteRequest $request,
        World $world,
        Campaign $campaign,
        PlayerNote $playerNote
    ): RedirectResponse {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensurePlayerNoteBelongsToCampaign($campaign, $playerNote);
        $this->authorize('update', $playerNote);

        $actor = $this->authenticatedUser($request);
        $data = $request->validated();

        $this->updatePlayerNoteAction->execute($playerNote, $campaign, $actor, $data);

        return redirect()
            ->route('campaigns.player-notes.show', [
                'world' => $world,
                'campaign' => $campaign,
                'playerNote' => $playerNote,
            ])
            ->with('status', 'Notiz aktualisiert.');
    }

    public function destroy(World $world, Campaign $campaign, PlayerNote $playerNote): RedirectResponse
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensurePlayerNoteBelongsToCampaign($campaign, $playerNote);
        $this->authorize('delete', $playerNote);

        $this->deletePlayerNoteAction->execute($playerNote);

        return redirect()
            ->route('campaigns.player-notes.index', [
                'world' => $world,
                'campaign' => $campaign,
            ])
            ->with('status', 'Notiz gelöscht.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Character>
     */
    private function characterOptionsForCampaignAndUser(Campaign $campaign, \App\Models\User $user): \Illuminate\Database\Eloquent\Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Character> $characters */
        $characters = $user->characters()
            ->where('world_id', (int) $campaign->world_id)
            ->orderBy('name')
            ->get(['id', 'name', 'world_id']);

        return $characters;
    }
}
