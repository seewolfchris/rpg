<?php

declare(strict_types=1);

namespace App\Actions\Scene;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\CombatPhase;
use App\Models\CombatPhaseAction;
use App\Models\Handout;
use App\Models\PlayerNote;
use App\Models\Scene;
use App\Models\StoryLogEntry;
use App\Models\User;
use App\Support\SensitiveFeatureGate;
use Illuminate\Support\Collection;

class BuildSceneShowPanelDataAction
{
    public function __construct(
        private readonly CampaignParticipantResolver $campaignParticipantResolver,
    ) {}

    /**
     * @return array{
     *     characters: \Illuminate\Database\Eloquent\Collection<int, Character>,
     *     probeCharacters: Collection<int, Character>,
     *     sceneHandouts: Collection<int, Handout>,
     *     openCombatPhase: CombatPhase|null,
     *     openCombatPhaseActions: Collection<int, CombatPhaseAction>,
     *     sceneChronicleCount: int,
     *     scenePlayerNotesCount: int,
     *     canModerateScene: bool
     * }
     */
    public function execute(Campaign $campaign, Scene $scene, User $user): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Character> $characters */
        $characters = $user
            ->characters()
            ->where('world_id', $campaign->world_id)
            ->orderBy('name')
            ->get();

        $canModerateScene = $campaign->canModeratePosts($user);
        $probeCharacters = $canModerateScene
            ? $this->campaignParticipantResolver->probeCharacters($campaign)
            : collect();
        [$openCombatPhase, $openCombatPhaseActions] = $this->openCombatPhaseData(
            scene: $scene,
            canModerateScene: $canModerateScene,
        );
        $sceneHandouts = $this->sceneHandouts($campaign, $scene, $user);
        $sceneChronicleCount = $this->sceneChronicleCount($campaign, $scene, $user);
        $scenePlayerNotesCount = $this->scenePlayerNotesCount($campaign, $scene, $user);

        return [
            'characters' => $characters,
            'probeCharacters' => $probeCharacters,
            'sceneHandouts' => $sceneHandouts,
            'openCombatPhase' => $openCombatPhase,
            'openCombatPhaseActions' => $openCombatPhaseActions,
            'sceneChronicleCount' => $sceneChronicleCount,
            'scenePlayerNotesCount' => $scenePlayerNotesCount,
            'canModerateScene' => $canModerateScene,
        ];
    }

    /**
     * @return array{0: CombatPhase|null, 1: Collection<int, CombatPhaseAction>}
     */
    private function openCombatPhaseData(Scene $scene, bool $canModerateScene): array
    {
        if (! $canModerateScene || ! SensitiveFeatureGate::enabled('features.combat_tools_enabled', false)) {
            return [null, collect()];
        }

        /** @var CombatPhase|null $openPhase */
        $openPhase = CombatPhase::query()
            ->where('scene_id', (int) $scene->id)
            ->where('status', CombatPhase::STATUS_COLLECTING)
            ->orderByDesc('phase_number')
            ->orderByDesc('id')
            ->with([
                'actions' => static function ($query): void {
                    $query->orderBy('position')->orderBy('id');
                },
                'actions.actorCharacter:id,name',
                'actions.targetCharacter:id,name',
            ])
            ->first();

        if (! $openPhase instanceof CombatPhase) {
            return [null, collect()];
        }

        /** @var Collection<int, CombatPhaseAction> $actions */
        $actions = $openPhase->actions;

        return [$openPhase, $actions];
    }

    /**
     * @return Collection<int, Handout>
     */
    private function sceneHandouts(Campaign $campaign, Scene $scene, User $user): Collection
    {
        $canManageCampaign = $campaign->canManageCampaign($user);

        /** @var Collection<int, Handout> $handouts */
        $handouts = Handout::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where(function ($query) use ($scene): void {
                $query
                    ->whereNull('scene_id')
                    ->orWhere('scene_id', (int) $scene->id);
            })
            ->when(
                ! $canManageCampaign,
                fn ($query) => $query->whereNotNull('revealed_at')
            )
            ->orderByRaw('sort_order IS NULL')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get([
                'id',
                'campaign_id',
                'scene_id',
                'title',
                'revealed_at',
                'sort_order',
                'created_at',
            ]);

        return $handouts;
    }

    private function sceneChronicleCount(Campaign $campaign, Scene $scene, User $user): int
    {
        $canManageCampaign = $campaign->canManageCampaign($user);

        return StoryLogEntry::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where(function ($query) use ($scene): void {
                $query
                    ->whereNull('scene_id')
                    ->orWhere('scene_id', (int) $scene->id);
            })
            ->when(
                ! $canManageCampaign,
                fn ($query) => $query->whereNotNull('revealed_at')
            )
            ->count();
    }

    private function scenePlayerNotesCount(Campaign $campaign, Scene $scene, User $user): int
    {
        return PlayerNote::query()
            ->where('campaign_id', (int) $campaign->id)
            ->where('user_id', (int) $user->id)
            ->where(function ($query) use ($scene): void {
                $query
                    ->whereNull('scene_id')
                    ->orWhere('scene_id', (int) $scene->id);
            })
            ->count();
    }
}
