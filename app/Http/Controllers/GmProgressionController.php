<?php

namespace App\Http\Controllers;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Domain\Character\CharacterProgressionService;
use App\Http\Requests\Gm\StoreCharacterProgressionAwardRequest;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class GmProgressionController extends Controller
{
    public function __construct(
        private readonly CharacterProgressionService $progressionService,
        private readonly CampaignParticipantResolver $campaignParticipantResolver,
    ) {}

    public function index(Request $request, World $world): View
    {
        $user = $this->authenticatedUser($request);
        $campaigns = $this->campaignsForUserAndWorld($user, $world);

        if (! $user->isGmOrAdmin() && $campaigns->isEmpty()) {
            abort(403);
        }

        $selectedCampaignId = (int) $request->query('campaign_id', 0);
        /** @var Campaign|null $selectedCampaign */
        $selectedCampaign = $campaigns->firstWhere('id', $selectedCampaignId) ?? $campaigns->first();

        $scenes = $selectedCampaign
            ? $selectedCampaign->scenes()
                ->orderBy('title')
                ->get(['id', 'title'])
            : collect();

        $characters = $selectedCampaign
            ? $this->campaignParticipantResolver->probeCharacters($selectedCampaign)
            : collect();

        return view('gm.progression', [
            'world' => $world,
            'campaigns' => $campaigns,
            'selectedCampaign' => $selectedCampaign,
            'scenes' => $scenes,
            'characters' => $characters,
            'milestoneSuggestions' => (array) config('character_progression.gm_xp_defaults.milestone_suggestions', []),
            'correctionSuggestions' => (array) config('character_progression.gm_xp_defaults.correction_suggestions', []),
        ]);
    }

    public function awardXp(StoreCharacterProgressionAwardRequest $request, World $world): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $campaignId = (int) $request->validated('campaign_id');
        /** @var Campaign $campaign */
        $campaign = Campaign::query()
            ->whereKey($campaignId)
            ->where('world_id', $world->id)
            ->firstOrFail();

        $sceneId = (int) ($request->validated('scene_id') ?? 0);
        $scene = $sceneId > 0
            ? Scene::query()
                ->whereKey($sceneId)
                ->where('campaign_id', $campaign->id)
                ->firstOrFail()
            : null;

        $result = $this->progressionService->awardXpBatch(
            actor: $user,
            campaign: $campaign,
            scene: $scene,
            eventMode: (string) $request->validated('event_mode'),
            awards: (array) $request->validated('awards'),
            reason: (string) $request->validated('reason', ''),
        );

        return redirect()
            ->route('gm.progression.index', [
                'world' => $world,
                'campaign_id' => $campaign->id,
            ])
            ->with(
                'status',
                'XP-Vergabe gespeichert. Betroffene Charaktere: '.$result['affected_characters'].', XP-Summe: '.$result['total_xp_delta'].'.'
            );
    }

    /**
     * @return Collection<int, Campaign>
     */
    private function campaignsForUserAndWorld(User $user, World $world): Collection
    {
        return Campaign::query()
            ->forWorld($world)
            ->when(
                ! $user->isGmOrAdmin(),
                fn ($query) => $query->whereHas(
                    'invitations',
                    fn ($invitationQuery) => $invitationQuery
                        ->where('user_id', $user->id)
                        ->where('status', CampaignInvitation::STATUS_ACCEPTED)
                        ->where('role', CampaignInvitation::ROLE_CO_GM)
                )
            )
            ->orderBy('title')
            ->get(['id', 'title', 'world_id']);
    }
}
