<?php

declare(strict_types=1);

namespace App\Domain\Combat;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Domain\Combat\Data\CombatActionInput;
use App\Domain\Combat\Data\CombatActionResult;
use App\Domain\Combat\Data\CombatActor;
use App\Domain\Combat\Data\CombatTarget;
use App\Domain\Combat\Exceptions\CombatInvariantViolationException;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Scene;
use App\Support\ProbeRoller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type CombatEntityContext array{
 *     type: string,
 *     character_id: int|null,
 *     character: Character|null,
 *     name: string,
 *     snapshot: array<string, mixed>
 * }
 * @phpstan-type CombatCharacterOutcome array{
 *     target_snapshot_before: array<string, mixed>,
 *     target_snapshot_after: array<string, mixed>,
 *     applied_le_delta: int,
 *     resulting_le_current: int|null,
 *     resulting_le_max: int|null
 * }
 */
class CombatService
{
    public function __construct(
        private readonly ProbeRoller $probeRoller,
        private readonly CampaignParticipantResolver $campaignParticipantResolver,
    ) {}

    /**
     * @throws CombatInvariantViolationException
     */
    public function resolveSingleAction(CombatActionInput $input): CombatActionResult
    {
        $campaign = $input->campaign;
        $scene = $input->scene;

        $this->assertSceneCampaignScope($campaign, $scene);

        $participantUserIds = $this->campaignParticipantResolver->participantUserIds($campaign);
        $actorContext = $this->resolveActorContext($input->actor, $campaign, $participantUserIds);
        $targetContext = $this->resolveTargetContext($input->target, $campaign, $participantUserIds);

        $weaponName = $this->trimNullable($input->weaponName);
        $attackTargetValue = $this->clampInt($input->attackTargetValue, 0, 100);
        $attackModifier = $this->clampInt($input->attackModifier, -100, 100);
        $attackRoll = $this->probeRoller->roll($input->attackRollMode, $attackModifier);
        $attackSuccess = (int) $attackRoll['total'] <= $attackTargetValue;

        $defenseLabel = $this->trimNullable($input->defenseLabel);
        $defenseTargetValue = $input->defenseTargetValue !== null
            ? $this->clampInt($input->defenseTargetValue, 0, 100)
            : null;
        $defenseModifier = $this->clampInt($input->defenseModifier, -100, 100);
        $defenseAttempted = $attackSuccess && $defenseTargetValue !== null;
        $defenseRoll = null;
        $defenseSuccess = null;

        if ($defenseAttempted) {
            $defenseRoll = $this->probeRoller->roll($input->defenseRollMode, $defenseModifier);
            $defenseSuccess = (int) $defenseRoll['total'] <= (int) $defenseTargetValue;
        }

        $attackHit = $attackSuccess;
        $defensePreventedHit = $defenseSuccess === true;
        $rawDamage = max(0, $input->damage);
        $armorProtection = $this->resolveArmorProtection(
            requestedArmorProtection: $input->armorProtection,
            targetCharacter: $targetContext['character'],
            targetSnapshot: $targetContext['snapshot'],
        );
        $effectiveDamage = ($attackHit && ! $defensePreventedHit)
            ? max(0, $rawDamage - $armorProtection)
            : 0;

        $targetSnapshotBefore = $targetContext['snapshot'];
        $targetSnapshotAfter = $targetSnapshotBefore;
        $appliedLeDelta = 0;
        $resultingLeCurrent = $this->snapshotInt($targetSnapshotBefore, 'le_current');
        $resultingLeMax = $this->snapshotInt($targetSnapshotBefore, 'le_max');

        if ($targetContext['type'] === CombatTarget::TYPE_CHARACTER && $targetContext['character'] instanceof Character) {
            $characterOutcome = $this->resolveCharacterTargetOutcome(
                campaign: $campaign,
                participantUserIds: $participantUserIds,
                targetCharacter: $targetContext['character'],
                effectiveDamage: $effectiveDamage,
            );

            $targetSnapshotBefore = $characterOutcome['target_snapshot_before'];
            $targetSnapshotAfter = $characterOutcome['target_snapshot_after'];
            $appliedLeDelta = $characterOutcome['applied_le_delta'];
            $resultingLeCurrent = $characterOutcome['resulting_le_current'];
            $resultingLeMax = $characterOutcome['resulting_le_max'];
        } elseif ($targetContext['type'] === CombatTarget::TYPE_NPC) {
            [$targetSnapshotAfter, $resultingLeCurrent, $resultingLeMax] = $this->resolveNpcTargetOutcome(
                targetSnapshotBefore: $targetSnapshotBefore,
                effectiveDamage: $effectiveDamage,
            );
        }

        $attackData = [
            'target_value' => $attackTargetValue,
            'roll_mode' => (string) $attackRoll['mode'],
            'rolls' => array_values((array) $attackRoll['rolls']),
            'kept_roll' => (int) $attackRoll['kept_roll'],
            'modifier' => (int) $attackRoll['modifier'],
            'total' => (int) $attackRoll['total'],
            'is_success' => $attackSuccess,
            'critical_success' => (bool) $attackRoll['critical_success'],
            'critical_failure' => (bool) $attackRoll['critical_failure'],
        ];

        $defenseData = [
            'attempted' => $defenseAttempted,
            'label' => $defenseLabel,
            'target_value' => $defenseTargetValue,
            'roll_mode' => $defenseAttempted ? (string) ($defenseRoll['mode'] ?? '') : null,
            'rolls' => $defenseAttempted ? array_values((array) ($defenseRoll['rolls'] ?? [])) : [],
            'kept_roll' => $defenseAttempted ? (int) ($defenseRoll['kept_roll'] ?? 0) : null,
            'modifier' => $defenseModifier,
            'total' => $defenseAttempted ? (int) ($defenseRoll['total'] ?? 0) : null,
            'is_success' => $defenseSuccess,
            'critical_success' => $defenseAttempted ? (bool) ($defenseRoll['critical_success'] ?? false) : false,
            'critical_failure' => $defenseAttempted ? (bool) ($defenseRoll['critical_failure'] ?? false) : false,
        ];

        $outcomeData = [
            'attack_hit' => $attackHit,
            'defense_prevented_hit' => $defensePreventedHit,
            'raw_damage' => $rawDamage,
            'armor_protection' => $armorProtection,
            'effective_damage' => $effectiveDamage,
            'applied_le_delta' => $appliedLeDelta,
            'resulting_le_current' => $resultingLeCurrent,
            'resulting_le_max' => $resultingLeMax,
        ];

        $snapshotsData = [
            'actor_snapshot' => $actorContext['snapshot'],
            'target_snapshot_before' => $targetSnapshotBefore,
            'target_snapshot_after' => $targetSnapshotAfter,
        ];

        $summary = $this->buildSummary(
            actorName: $actorContext['name'],
            targetName: $targetContext['name'],
            attackHit: $attackHit,
            defensePreventedHit: $defensePreventedHit,
            effectiveDamage: $effectiveDamage,
        );
        $logLines = $this->buildLogLines(
            actorName: $actorContext['name'],
            targetName: $targetContext['name'],
            attackData: $attackData,
            defenseData: $defenseData,
            outcomeData: $outcomeData,
            intentText: $this->trimNullable($input->intentText),
            resolutionNote: $this->trimNullable($input->resolutionNote),
        );

        return new CombatActionResult(
            actorType: $actorContext['type'],
            actorCharacterId: $actorContext['character_id'],
            actorName: $actorContext['name'],
            targetType: $targetContext['type'],
            targetCharacterId: $targetContext['character_id'],
            targetName: $targetContext['name'],
            weaponName: $weaponName,
            attack: $attackData,
            defense: $defenseData,
            outcome: $outcomeData,
            snapshots: $snapshotsData,
            summary: $summary,
            logLines: $logLines,
        );
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
     * @param  Collection<int, int<1, max>>  $participantUserIds
     * @return CombatEntityContext
     *
     * @throws CombatInvariantViolationException
     */
    private function resolveActorContext(CombatActor $actor, Campaign $campaign, Collection $participantUserIds): array
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
            $snapshot = $this->buildCharacterSnapshot($actor->character, $actor->snapshot);

            return [
                'type' => CombatActor::TYPE_CHARACTER,
                'character_id' => (int) $actor->character->id,
                'character' => $actor->character,
                'name' => $name,
                'snapshot' => $snapshot,
            ];
        }

        $name = $actor->resolvedName();
        if ($name === '') {
            throw CombatInvariantViolationException::actorNpcNameMissing();
        }

        $snapshot = $this->buildNpcSnapshot($name, $actor->snapshot);

        return [
            'type' => CombatActor::TYPE_NPC,
            'character_id' => null,
            'character' => null,
            'name' => $name,
            'snapshot' => $snapshot,
        ];
    }

    /**
     * @param  Collection<int, int<1, max>>  $participantUserIds
     * @return CombatEntityContext
     *
     * @throws CombatInvariantViolationException
     */
    private function resolveTargetContext(CombatTarget $target, Campaign $campaign, Collection $participantUserIds): array
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
            $snapshot = $this->buildCharacterSnapshot($target->character, $target->snapshot);

            return [
                'type' => CombatTarget::TYPE_CHARACTER,
                'character_id' => (int) $target->character->id,
                'character' => $target->character,
                'name' => $name,
                'snapshot' => $snapshot,
            ];
        }

        $name = $target->resolvedName();
        if ($name === '') {
            throw CombatInvariantViolationException::targetNpcNameMissing();
        }

        $snapshot = $this->buildNpcSnapshot($name, $target->snapshot);

        return [
            'type' => CombatTarget::TYPE_NPC,
            'character_id' => null,
            'character' => null,
            'name' => $name,
            'snapshot' => $snapshot,
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
        $campaignWorldId = (int) $campaign->world_id;

        if ($targetUserId < 1 || ! $participantUserIds->contains($targetUserId)) {
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

        if ((int) $character->world_id !== $campaignWorldId) {
            if ($isActor) {
                throw CombatInvariantViolationException::actorCharacterWorldMismatch(
                    characterId: $characterId,
                    characterWorldId: (int) $character->world_id,
                    campaignWorldId: $campaignWorldId,
                );
            }

            throw CombatInvariantViolationException::targetCharacterWorldMismatch(
                characterId: $characterId,
                characterWorldId: (int) $character->world_id,
                campaignWorldId: $campaignWorldId,
            );
        }
    }

    /**
     * @param  Collection<int, int<1, max>>  $participantUserIds
     * @return CombatCharacterOutcome
     *
     * @throws CombatInvariantViolationException
     */
    private function resolveCharacterTargetOutcome(
        Campaign $campaign,
        Collection $participantUserIds,
        Character $targetCharacter,
        int $effectiveDamage,
    ): array {
        return DB::transaction(function () use ($campaign, $participantUserIds, $targetCharacter, $effectiveDamage): array {
            $lockedTarget = Character::query()
                ->lockForUpdate()
                ->find((int) $targetCharacter->id);

            if (! $lockedTarget instanceof Character) {
                throw CombatInvariantViolationException::targetCharacterNotFound((int) $targetCharacter->id);
            }

            $this->assertCharacterCampaignContext(
                character: $lockedTarget,
                campaign: $campaign,
                participantUserIds: $participantUserIds,
                isActor: false,
            );

            $snapshotBefore = $this->buildCharacterSnapshot($lockedTarget);
            $appliedLeDelta = 0;
            $resultingLeCurrent = $this->snapshotInt($snapshotBefore, 'le_current');
            $resultingLeMax = $this->snapshotInt($snapshotBefore, 'le_max');

            if ($effectiveDamage > 0) {
                [$appliedLeDelta, $resultingLeCurrent, $resultingLeMax] = $this->applyLeDamage(
                    character: $lockedTarget,
                    damage: $effectiveDamage,
                );

                if ($lockedTarget->isDirty('le_current')) {
                    $lockedTarget->save();
                }
            }

            $snapshotAfter = $this->buildCharacterSnapshot($lockedTarget);
            if ($effectiveDamage <= 0) {
                $resultingLeCurrent = $this->snapshotInt($snapshotAfter, 'le_current');
                $resultingLeMax = $this->snapshotInt($snapshotAfter, 'le_max');
            }

            return [
                'target_snapshot_before' => $snapshotBefore,
                'target_snapshot_after' => $snapshotAfter,
                'applied_le_delta' => $appliedLeDelta,
                'resulting_le_current' => $resultingLeCurrent,
                'resulting_le_max' => $resultingLeMax,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $targetSnapshotBefore
     * @return array{0: array<string, mixed>, 1: int|null, 2: int|null}
     */
    private function resolveNpcTargetOutcome(array $targetSnapshotBefore, int $effectiveDamage): array
    {
        $targetSnapshotAfter = $targetSnapshotBefore;
        $current = $this->snapshotInt($targetSnapshotBefore, 'le_current');
        $max = $this->snapshotInt($targetSnapshotBefore, 'le_max');

        if ($current === null) {
            return [$targetSnapshotAfter, null, $max];
        }

        $effectiveMax = max($max ?? $current, 0);
        $resolvedCurrent = $this->clampInt($current, 0, $effectiveMax);
        $resulting = $this->clampInt($resolvedCurrent - $effectiveDamage, 0, $effectiveMax);

        $targetSnapshotAfter['le_current'] = $resulting;
        $targetSnapshotAfter['resulting_le_current'] = $resulting;
        if ($max !== null) {
            $targetSnapshotAfter['le_max'] = $effectiveMax;
        }

        return [$targetSnapshotAfter, $resulting, $max !== null ? $effectiveMax : null];
    }

    /**
     * @param  array<string, mixed>  $seed
     * @return array<string, mixed>
     */
    private function buildCharacterSnapshot(Character $character, array $seed = []): array
    {
        $leMaxRaw = $character->le_max;
        $leMax = $leMaxRaw === null ? null : max(0, (int) $leMaxRaw);
        $leCurrentRaw = $character->le_current;
        $leCurrent = $leCurrentRaw === null
            ? $leMax
            : ($leMax === null
                ? max(0, (int) $leCurrentRaw)
                : $this->clampInt((int) $leCurrentRaw, 0, $leMax));

        $snapshot = $seed;
        $snapshot['character_id'] = (int) $character->id;
        $snapshot['name'] = (string) $character->name;
        $snapshot['le_current'] = $leCurrent;
        $snapshot['le_max'] = $leMax;
        $snapshot['armor_rs'] = max(0, $character->armorProtectionValue());

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $seed
     * @return array<string, mixed>
     */
    private function buildNpcSnapshot(string $name, array $seed = []): array
    {
        $snapshot = $seed;
        $snapshot['name'] = $name;

        return $snapshot;
    }

    /**
     * @return array{0: int, 1: int|null, 2: int|null}
     */
    private function applyLeDamage(Character $character, int $damage): array
    {
        $rawMax = $character->le_max;
        $rawCurrent = $character->le_current;

        if ($rawMax === null && $rawCurrent === null) {
            return [0, null, null];
        }

        $maxValue = max((int) ($rawMax ?? $rawCurrent ?? 0), 0);
        $currentValue = $this->clampInt((int) ($rawCurrent ?? $maxValue), 0, $maxValue);
        $resultingValue = $this->clampInt($currentValue - $damage, 0, $maxValue);
        $appliedDelta = $resultingValue - $currentValue;

        if ($rawCurrent !== $resultingValue) {
            /** @var int<0, max> $normalizedResultingValue */
            $normalizedResultingValue = max(0, $resultingValue);
            $character->le_current = $normalizedResultingValue;
        }

        return [$appliedDelta, $resultingValue, $maxValue];
    }

    /**
     * @param  array<string, mixed>  $targetSnapshot
     */
    private function resolveArmorProtection(?int $requestedArmorProtection, ?Character $targetCharacter, array $targetSnapshot): int
    {
        if ($requestedArmorProtection !== null) {
            return max(0, $requestedArmorProtection);
        }

        if ($targetCharacter instanceof Character) {
            return max(0, $targetCharacter->armorProtectionValue());
        }

        foreach (['armor_protection', 'armor_rs', 'protection'] as $key) {
            $value = $this->snapshotInt($targetSnapshot, $key);
            if ($value !== null) {
                return max(0, $value);
            }
        }

        return 0;
    }

    /**
     * @param  array{
     *     target_value: int,
     *     roll_mode: string,
     *     rolls: list<int>,
     *     kept_roll: int,
     *     modifier: int,
     *     total: int,
     *     is_success: bool,
     *     critical_success: bool,
     *     critical_failure: bool
     * }  $attackData
     * @param  array{
     *     attempted: bool,
     *     label: string|null,
     *     target_value: int|null,
     *     roll_mode: string|null,
     *     rolls: list<int>,
     *     kept_roll: int|null,
     *     modifier: int,
     *     total: int|null,
     *     is_success: bool|null,
     *     critical_success: bool,
     *     critical_failure: bool
     * }  $defenseData
     * @param  array{
     *     attack_hit: bool,
     *     defense_prevented_hit: bool,
     *     raw_damage: int,
     *     armor_protection: int,
     *     effective_damage: int,
     *     applied_le_delta: int,
     *     resulting_le_current: int|null,
     *     resulting_le_max: int|null
     * }  $outcomeData
     * @return list<string>
     */
    private function buildLogLines(
        string $actorName,
        string $targetName,
        array $attackData,
        array $defenseData,
        array $outcomeData,
        ?string $intentText,
        ?string $resolutionNote,
    ): array {
        $lines = [];

        if ($intentText !== null && $intentText !== '') {
            $lines[] = 'Absicht: '.$intentText;
        }

        $lines[] = sprintf(
            'Angriff %s -> %s: Wurf %d %+d = %d gegen %d (%s).',
            $actorName,
            $targetName,
            (int) $attackData['kept_roll'],
            (int) $attackData['modifier'],
            (int) $attackData['total'],
            (int) $attackData['target_value'],
            (bool) $attackData['is_success'] ? 'Erfolg' : 'Misserfolg'
        );

        if ((bool) $defenseData['attempted']) {
            $lines[] = sprintf(
                'Verteidigung%s: Wurf %d %+d = %d gegen %d (%s).',
                $defenseData['label'] !== null && $defenseData['label'] !== ''
                    ? ' ['.$defenseData['label'].']'
                    : '',
                (int) ($defenseData['kept_roll'] ?? 0),
                (int) $defenseData['modifier'],
                (int) ($defenseData['total'] ?? 0),
                (int) ($defenseData['target_value'] ?? 0),
                ($defenseData['is_success'] ?? false) ? 'Erfolg' : 'Misserfolg'
            );
        }

        $lines[] = sprintf(
            'Schaden: roh %d, RS %d, effektiv %d.',
            (int) $outcomeData['raw_damage'],
            (int) $outcomeData['armor_protection'],
            (int) $outcomeData['effective_damage']
        );

        if ($outcomeData['resulting_le_current'] !== null && $outcomeData['resulting_le_max'] !== null) {
            $lines[] = sprintf(
                'LE: Delta %d, neu %d/%d.',
                (int) $outcomeData['applied_le_delta'],
                (int) $outcomeData['resulting_le_current'],
                (int) $outcomeData['resulting_le_max']
            );
        }

        if ($resolutionNote !== null && $resolutionNote !== '') {
            $lines[] = 'SL-Notiz: '.$resolutionNote;
        }

        return $lines;
    }

    private function buildSummary(
        string $actorName,
        string $targetName,
        bool $attackHit,
        bool $defensePreventedHit,
        int $effectiveDamage,
    ): string {
        if (! $attackHit) {
            return sprintf('%s verfehlt %s.', $actorName, $targetName);
        }

        if ($defensePreventedHit) {
            return sprintf('%s trifft %s, aber die Verteidigung verhindert Schaden.', $actorName, $targetName);
        }

        if ($effectiveDamage <= 0) {
            return sprintf('%s trifft %s, aber es entsteht kein Schaden.', $actorName, $targetName);
        }

        return sprintf('%s trifft %s fuer %d LE-Schaden.', $actorName, $targetName, $effectiveDamage);
    }

    private function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($value, $max));
    }

    private function trimNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function snapshotInt(array $snapshot, string $key): ?int
    {
        if (! array_key_exists($key, $snapshot)) {
            return null;
        }

        $value = $snapshot[$key];
        if (! is_int($value) && ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
