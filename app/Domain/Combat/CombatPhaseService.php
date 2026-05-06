<?php

declare(strict_types=1);

namespace App\Domain\Combat;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Domain\Combat\Data\CombatActionInput;
use App\Domain\Combat\Data\CombatActor;
use App\Domain\Combat\Data\CombatPhaseResolutionResult;
use App\Domain\Combat\Data\CombatTarget;
use App\Domain\Combat\Exceptions\CombatInvariantViolationException;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\CombatPhase;
use App\Models\CombatPhaseAction;
use App\Models\DiceRoll;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type CombatEntityPayload array{
 *     type: string,
 *     character_id: int|null,
 *     name: string,
 *     snapshot: array<string, mixed>
 * }
 * @phpstan-type CombatPhaseResultItem array{
 *     action_id: int,
 *     position: int,
 *     result: array<string, mixed>
 * }
 */
class CombatPhaseService
{
    public function __construct(
        private readonly CombatService $combatService,
        private readonly CampaignParticipantResolver $campaignParticipantResolver,
    ) {}

    /**
     * @throws CombatInvariantViolationException
     */
    public function startPhase(Campaign $campaign, Scene $scene, User $startedBy): CombatPhase
    {
        $this->assertSceneCampaignScope($campaign, $scene);
        $this->assertCanModerate($campaign, $startedBy);

        return DB::transaction(function () use ($campaign, $scene, $startedBy): CombatPhase {
            $lockedScene = Scene::query()
                ->whereKey((int) $scene->id)
                ->lockForUpdate()
                ->first(['id', 'campaign_id']);

            if (! $lockedScene instanceof Scene || (int) $lockedScene->campaign_id !== (int) $campaign->id) {
                throw CombatInvariantViolationException::sceneCampaignMismatch(
                    sceneCampaignId: (int) $scene->campaign_id,
                    campaignId: (int) $campaign->id,
                );
            }

            $phaseRows = CombatPhase::query()
                ->where('scene_id', (int) $lockedScene->id)
                ->lockForUpdate()
                ->get(['id', 'phase_number', 'status']);

            $openPhase = $phaseRows->first(static fn (CombatPhase $phase): bool => $phase->isCollecting());
            if ($openPhase instanceof CombatPhase) {
                throw CombatInvariantViolationException::phaseAlreadyCollecting(
                    sceneId: (int) $lockedScene->id,
                    existingPhaseId: (int) $openPhase->id,
                );
            }

            $nextPhaseNumber = ((int) $phaseRows->max('phase_number')) + 1;
            if ($nextPhaseNumber < 1) {
                $nextPhaseNumber = 1;
            }

            return CombatPhase::query()->create([
                'campaign_id' => (int) $campaign->id,
                'scene_id' => (int) $lockedScene->id,
                'phase_number' => $nextPhaseNumber,
                'status' => CombatPhase::STATUS_COLLECTING,
                'started_by' => (int) $startedBy->id,
                'resolved_by' => null,
                'resolved_at' => null,
                'resolution_summary' => null,
            ]);
        });
    }

    /**
     * @throws CombatInvariantViolationException
     */
    public function queueAction(CombatPhase $phase, CombatActionInput $input): CombatPhaseAction
    {
        $this->assertSceneCampaignScope($input->campaign, $input->scene);
        $this->assertPhaseScope($phase, $input->campaign, $input->scene);
        $this->assertPhaseCollecting($phase);

        $participantUserIds = $this->campaignParticipantResolver->participantUserIds($input->campaign);
        $actor = $this->normalizeActorForQueue($input->actor, $input->campaign, $participantUserIds);
        $target = $this->normalizeTargetForQueue($input->target, $input->campaign, $participantUserIds);

        $attributes = [
            'actor_type' => $actor['type'],
            'actor_character_id' => $actor['character_id'],
            'actor_name' => $actor['name'],
            'actor_snapshot' => $actor['snapshot'],
            'target_type' => $target['type'],
            'target_character_id' => $target['character_id'],
            'target_name' => $target['name'],
            'target_snapshot' => $target['snapshot'],
            'weapon_name' => $this->trimNullable($input->weaponName),
            'attack_target_value' => $this->clampInt($input->attackTargetValue, 0, 100),
            'attack_roll_mode' => $this->normalizeRollMode($input->attackRollMode),
            'attack_modifier' => $this->clampInt($input->attackModifier, -100, 100),
            'defense_label' => $this->trimNullable($input->defenseLabel),
            'defense_target_value' => $input->defenseTargetValue !== null
                ? $this->clampInt($input->defenseTargetValue, 0, 100)
                : null,
            'defense_roll_mode' => $input->defenseTargetValue !== null
                ? $this->normalizeRollMode($input->defenseRollMode)
                : null,
            'defense_modifier' => $this->clampInt($input->defenseModifier, -100, 100),
            'damage' => $this->clampInt($input->damage, 0, 65535),
            'armor_protection' => $input->armorProtection !== null
                ? $this->clampInt($input->armorProtection, 0, 65535)
                : null,
            'intent_text' => $this->trimNullable($input->intentText),
            'resolution_note' => $this->trimNullable($input->resolutionNote),
            'result' => null,
            'resolved_at' => null,
        ];

        return DB::transaction(function () use ($phase, $attributes): CombatPhaseAction {
            $lockedPhase = CombatPhase::query()->lockForUpdate()->find((int) $phase->id);
            if (! $lockedPhase instanceof CombatPhase) {
                throw CombatInvariantViolationException::phaseMissing((int) $phase->id);
            }

            $this->assertPhaseCollecting($lockedPhase);

            $maxPosition = CombatPhaseAction::query()
                ->where('combat_phase_id', (int) $lockedPhase->id)
                ->lockForUpdate()
                ->max('position');

            $nextPosition = ((int) $maxPosition) + 1;
            if ($nextPosition < 1) {
                $nextPosition = 1;
            }

            /** @var CombatPhaseAction $action */
            $action = $lockedPhase->actions()->create(array_merge($attributes, [
                'position' => $nextPosition,
            ]));

            return $action;
        });
    }

    /**
     * @throws CombatInvariantViolationException
     */
    public function resolvePhase(CombatPhase $phase, User $resolvedBy): CombatPhaseResolutionResult
    {
        return DB::transaction(function () use ($phase, $resolvedBy): CombatPhaseResolutionResult {
            $lockedPhase = CombatPhase::query()
                ->with(['campaign', 'scene'])
                ->lockForUpdate()
                ->find((int) $phase->id);

            if (! $lockedPhase instanceof CombatPhase) {
                throw CombatInvariantViolationException::phaseMissing((int) $phase->id);
            }

            $this->assertPhaseCollecting($lockedPhase);

            $campaign = $lockedPhase->campaign;
            $scene = $lockedPhase->scene;

            if (! $campaign instanceof Campaign || ! $scene instanceof Scene) {
                throw CombatInvariantViolationException::phaseScopeMismatch(
                    phaseId: (int) $lockedPhase->id,
                    phaseCampaignId: (int) $lockedPhase->campaign_id,
                    phaseSceneId: (int) $lockedPhase->scene_id,
                    inputCampaignId: (int) $lockedPhase->campaign_id,
                    inputSceneId: (int) $lockedPhase->scene_id,
                );
            }

            $this->assertSceneCampaignScope($campaign, $scene);
            $this->assertCanModerate($campaign, $resolvedBy);

            $actions = CombatPhaseAction::query()
                ->where('combat_phase_id', (int) $lockedPhase->id)
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($actions->isEmpty()) {
                throw CombatInvariantViolationException::phaseHasNoActions((int) $lockedPhase->id);
            }

            $resolvedAt = now();

            /** @var list<CombatPhaseResultItem> $resultItems */
            $resultItems = [];

            foreach ($actions as $action) {
                $result = $this->combatService->resolveSingleAction($this->inputFromPhaseAction($action, $campaign, $scene));
                $resultArray = $result->toArray();

                $action->setAttribute('result', $resultArray);
                $action->setAttribute('resolved_at', $resolvedAt);
                $action->save();

                $resultItems[] = [
                    'action_id' => (int) $action->id,
                    'position' => (int) $action->position,
                    'result' => $resultArray,
                ];
            }

            $summary = $this->buildResolutionSummary($lockedPhase, $resultItems);

            $resolvedById = max(0, (int) $resolvedBy->id);

            $lockedPhase->setAttribute('status', CombatPhase::STATUS_RESOLVED);
            $lockedPhase->setAttribute('resolved_by', $resolvedById);
            $lockedPhase->setAttribute('resolved_at', $resolvedAt);
            $lockedPhase->setAttribute('resolution_summary', $summary);
            $lockedPhase->save();

            return new CombatPhaseResolutionResult(
                phaseId: (int) $lockedPhase->id,
                phaseNumber: (int) $lockedPhase->phase_number,
                actionCount: count($resultItems),
                resolvedAt: $resolvedAt->toIso8601String(),
                results: $resultItems,
                summary: (string) ($summary['summary'] ?? ''),
                summaryLines: array_values((array) ($summary['lines'] ?? [])),
            );
        });
    }

    /**
     * @param  list<CombatPhaseResultItem>  $resultItems
     * @return array{
     *     phase_number: int,
     *     action_count: int,
     *     hits: int,
     *     blocked_hits: int,
     *     misses: int,
     *     total_effective_damage: int,
     *     summary: string,
     *     lines: list<string>
     * }
     */
    private function buildResolutionSummary(CombatPhase $phase, array $resultItems): array
    {
        $hits = 0;
        $blockedHits = 0;
        $misses = 0;
        $totalEffectiveDamage = 0;

        foreach ($resultItems as $item) {
            $outcome = (array) data_get($item, 'result.outcome', []);
            $attackHit = (bool) ($outcome['attack_hit'] ?? false);
            $blocked = (bool) ($outcome['defense_prevented_hit'] ?? false);

            if (! $attackHit) {
                $misses++;
            } elseif ($blocked) {
                $blockedHits++;
            } else {
                $hits++;
            }

            $totalEffectiveDamage += max(0, (int) ($outcome['effective_damage'] ?? 0));
        }

        $actionCount = count($resultItems);
        $phaseNumber = (int) $phase->phase_number;

        $summary = sprintf(
            'Kampfphase %d ausgewertet: %d Aktionen, %d Treffer, %d abgewehrt, %d verfehlt, %d Gesamtschaden.',
            $phaseNumber,
            $actionCount,
            $hits,
            $blockedHits,
            $misses,
            $totalEffectiveDamage,
        );

        $lines = [
            'Aktionen: '.$actionCount,
            'Treffer: '.$hits,
            'Abgewehrt: '.$blockedHits,
            'Verfehlt: '.$misses,
            'Gesamtschaden: '.$totalEffectiveDamage,
        ];

        return [
            'phase_number' => $phaseNumber,
            'action_count' => $actionCount,
            'hits' => $hits,
            'blocked_hits' => $blockedHits,
            'misses' => $misses,
            'total_effective_damage' => $totalEffectiveDamage,
            'summary' => $summary,
            'lines' => $lines,
        ];
    }

    /**
     * @throws CombatInvariantViolationException
     */
    private function inputFromPhaseAction(CombatPhaseAction $action, Campaign $campaign, Scene $scene): CombatActionInput
    {
        return new CombatActionInput(
            campaign: $campaign,
            scene: $scene,
            actor: $this->actorFromPhaseAction($action),
            target: $this->targetFromPhaseAction($action),
            weaponName: $this->trimNullable($action->weapon_name),
            attackTargetValue: $this->clampInt((int) $action->attack_target_value, 0, 100),
            attackRollMode: $this->normalizeRollMode((string) $action->attack_roll_mode),
            attackModifier: $this->clampInt((int) $action->attack_modifier, -100, 100),
            defenseLabel: $this->trimNullable($action->defense_label),
            defenseTargetValue: $action->defense_target_value !== null
                ? $this->clampInt((int) $action->defense_target_value, 0, 100)
                : null,
            defenseRollMode: $this->normalizeRollMode((string) ($action->defense_roll_mode ?? DiceRoll::MODE_NORMAL)),
            defenseModifier: $this->clampInt((int) $action->defense_modifier, -100, 100),
            damage: $this->clampInt((int) $action->damage, 0, 65535),
            armorProtection: $action->armor_protection !== null
                ? $this->clampInt((int) $action->armor_protection, 0, 65535)
                : null,
            intentText: $this->trimNullable($action->intent_text),
            resolutionNote: $this->trimNullable($action->resolution_note),
        );
    }

    /**
     * @throws CombatInvariantViolationException
     */
    private function actorFromPhaseAction(CombatPhaseAction $action): CombatActor
    {
        if ((string) $action->actor_type === CombatPhaseAction::TYPE_NPC) {
            return new CombatActor(
                type: CombatActor::TYPE_NPC,
                character: null,
                name: $this->trimNullable($action->actor_name),
                snapshot: $this->normalizeSnapshot($action->actor_snapshot),
            );
        }

        if ((string) $action->actor_type !== CombatPhaseAction::TYPE_CHARACTER) {
            throw CombatInvariantViolationException::actorTypeInvalid((string) $action->actor_type);
        }

        $characterId = (int) ($action->actor_character_id ?? 0);
        $character = $characterId > 0
            ? Character::query()->find($characterId)
            : null;

        return new CombatActor(
            type: CombatActor::TYPE_CHARACTER,
            character: $character,
            name: $this->trimNullable($action->actor_name),
            snapshot: $this->normalizeSnapshot($action->actor_snapshot),
        );
    }

    /**
     * @throws CombatInvariantViolationException
     */
    private function targetFromPhaseAction(CombatPhaseAction $action): CombatTarget
    {
        if ((string) $action->target_type === CombatPhaseAction::TYPE_NPC) {
            return new CombatTarget(
                type: CombatTarget::TYPE_NPC,
                character: null,
                name: $this->trimNullable($action->target_name),
                snapshot: $this->normalizeSnapshot($action->target_snapshot),
            );
        }

        if ((string) $action->target_type !== CombatPhaseAction::TYPE_CHARACTER) {
            throw CombatInvariantViolationException::targetTypeInvalid((string) $action->target_type);
        }

        $characterId = (int) ($action->target_character_id ?? 0);
        $character = $characterId > 0
            ? Character::query()->find($characterId)
            : null;

        return new CombatTarget(
            type: CombatTarget::TYPE_CHARACTER,
            character: $character,
            name: $this->trimNullable($action->target_name),
            snapshot: $this->normalizeSnapshot($action->target_snapshot),
        );
    }

    /**
     * @param  Collection<int, int<1, max>>  $participantUserIds
     * @return CombatEntityPayload
     *
     * @throws CombatInvariantViolationException
     */
    private function normalizeActorForQueue(CombatActor $actor, Campaign $campaign, Collection $participantUserIds): array
    {
        if (! in_array($actor->type, [CombatActor::TYPE_CHARACTER, CombatActor::TYPE_NPC], true)) {
            throw CombatInvariantViolationException::actorTypeInvalid($actor->type);
        }

        if ($actor->isCharacter()) {
            if (! $actor->character instanceof Character || (int) $actor->character->id <= 0) {
                throw CombatInvariantViolationException::actorCharacterMissing();
            }

            $this->assertCharacterCampaignContext(
                character: $actor->character,
                campaign: $campaign,
                participantUserIds: $participantUserIds,
                isActor: true,
            );

            $name = $actor->resolvedName();
            $snapshot = $this->buildCharacterSnapshot($actor->character, $actor->snapshot, $name);

            return [
                'type' => CombatActor::TYPE_CHARACTER,
                'character_id' => (int) $actor->character->id,
                'name' => $name,
                'snapshot' => $snapshot,
            ];
        }

        $name = $actor->resolvedName();
        if ($name === '') {
            throw CombatInvariantViolationException::actorNpcNameMissing();
        }

        return [
            'type' => CombatActor::TYPE_NPC,
            'character_id' => null,
            'name' => $name,
            'snapshot' => $this->buildNpcSnapshot($name, $actor->snapshot),
        ];
    }

    /**
     * @param  Collection<int, int<1, max>>  $participantUserIds
     * @return CombatEntityPayload
     *
     * @throws CombatInvariantViolationException
     */
    private function normalizeTargetForQueue(CombatTarget $target, Campaign $campaign, Collection $participantUserIds): array
    {
        if (! in_array($target->type, [CombatTarget::TYPE_CHARACTER, CombatTarget::TYPE_NPC], true)) {
            throw CombatInvariantViolationException::targetTypeInvalid($target->type);
        }

        if ($target->isCharacter()) {
            if (! $target->character instanceof Character || (int) $target->character->id <= 0) {
                throw CombatInvariantViolationException::targetCharacterMissing();
            }

            $this->assertCharacterCampaignContext(
                character: $target->character,
                campaign: $campaign,
                participantUserIds: $participantUserIds,
                isActor: false,
            );

            $name = $target->resolvedName();
            $snapshot = $this->buildCharacterSnapshot($target->character, $target->snapshot, $name);

            return [
                'type' => CombatTarget::TYPE_CHARACTER,
                'character_id' => (int) $target->character->id,
                'name' => $name,
                'snapshot' => $snapshot,
            ];
        }

        $name = $target->resolvedName();
        if ($name === '') {
            throw CombatInvariantViolationException::targetNpcNameMissing();
        }

        return [
            'type' => CombatTarget::TYPE_NPC,
            'character_id' => null,
            'name' => $name,
            'snapshot' => $this->buildNpcSnapshot($name, $target->snapshot),
        ];
    }

    /**
     * @param  Collection<int, int<1, max>>  $participantUserIds
     *
     * @throws CombatInvariantViolationException
     */
    private function assertCharacterCampaignContext(
        Character $character,
        Campaign $campaign,
        Collection $participantUserIds,
        bool $isActor,
    ): void {
        $characterId = (int) $character->id;
        $targetUserId = (int) $character->user_id;
        $campaignId = (int) $campaign->id;

        if ((int) $character->world_id !== (int) $campaign->world_id) {
            if ($isActor) {
                throw CombatInvariantViolationException::actorCharacterWorldMismatch(
                    characterId: $characterId,
                    characterWorldId: (int) $character->world_id,
                    campaignWorldId: (int) $campaign->world_id,
                );
            }

            throw CombatInvariantViolationException::targetCharacterWorldMismatch(
                characterId: $characterId,
                characterWorldId: (int) $character->world_id,
                campaignWorldId: (int) $campaign->world_id,
            );
        }

        if (! $this->campaignParticipantResolver->isParticipantUserId($campaign, $targetUserId, $participantUserIds)) {
            if ($isActor) {
                throw CombatInvariantViolationException::actorCharacterNotParticipant(
                    characterId: $characterId,
                    targetUserId: $targetUserId,
                    campaignId: $campaignId,
                );
            }

            throw CombatInvariantViolationException::targetCharacterNotParticipant(
                characterId: $characterId,
                targetUserId: $targetUserId,
                campaignId: $campaignId,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function buildCharacterSnapshot(Character $character, array $snapshot, string $name): array
    {
        $defaults = [
            'character_id' => (int) $character->id,
            'name' => $name,
            'le_current' => $character->le_current !== null ? (int) $character->le_current : null,
            'le_max' => $character->le_max !== null ? (int) $character->le_max : null,
            'armor_rs' => $character->armorProtectionValue(),
        ];

        return array_replace($defaults, $this->normalizeSnapshot($snapshot));
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function buildNpcSnapshot(string $name, array $snapshot): array
    {
        return array_replace([
            'name' => $name,
        ], $this->normalizeSnapshot($snapshot));
    }

    /**
     * @param  array<string, mixed>|mixed  $snapshot
     * @return array<string, mixed>
     */
    private function normalizeSnapshot(mixed $snapshot): array
    {
        return is_array($snapshot) ? $snapshot : [];
    }

    /**
     * @throws CombatInvariantViolationException
     */
    private function assertSceneCampaignScope(Campaign $campaign, Scene $scene): void
    {
        if ((int) $scene->campaign_id !== (int) $campaign->id) {
            throw CombatInvariantViolationException::sceneCampaignMismatch(
                sceneCampaignId: (int) $scene->campaign_id,
                campaignId: (int) $campaign->id,
            );
        }
    }

    /**
     * @throws CombatInvariantViolationException
     */
    private function assertCanModerate(Campaign $campaign, User $user): void
    {
        if (! $this->campaignParticipantResolver->canModerateCampaign($user, $campaign)) {
            throw CombatInvariantViolationException::moderatorRequired(
                campaignId: (int) $campaign->id,
                userId: (int) $user->id,
            );
        }
    }

    /**
     * @throws CombatInvariantViolationException
     */
    private function assertPhaseCollecting(CombatPhase $phase): void
    {
        if (! $phase->isCollecting()) {
            throw CombatInvariantViolationException::phaseNotCollecting(
                phaseId: (int) $phase->id,
                status: (string) $phase->status,
            );
        }
    }

    /**
     * @throws CombatInvariantViolationException
     */
    private function assertPhaseScope(CombatPhase $phase, Campaign $campaign, Scene $scene): void
    {
        if ((int) $phase->campaign_id !== (int) $campaign->id || (int) $phase->scene_id !== (int) $scene->id) {
            throw CombatInvariantViolationException::phaseScopeMismatch(
                phaseId: (int) $phase->id,
                phaseCampaignId: (int) $phase->campaign_id,
                phaseSceneId: (int) $phase->scene_id,
                inputCampaignId: (int) $campaign->id,
                inputSceneId: (int) $scene->id,
            );
        }
    }

    private function normalizeRollMode(?string $mode): string
    {
        $candidate = trim((string) ($mode ?? ''));

        return in_array($candidate, DiceRoll::ALLOWED_MODES, true)
            ? $candidate
            : DiceRoll::MODE_NORMAL;
    }

    private function trimNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function clampInt(int $value, int $min, int $max): int
    {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}
