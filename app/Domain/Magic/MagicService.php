<?php

declare(strict_types=1);

namespace App\Domain\Magic;

use App\Domain\Campaign\CampaignParticipantResolver;
use App\Domain\Magic\Data\MagicActionInput;
use App\Domain\Magic\Data\MagicActionResult;
use App\Domain\Magic\Data\MagicActor;
use App\Domain\Magic\Exceptions\MagicInvariantViolationException;
use App\Models\Character;
use App\Support\ProbeRoller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type MagicEntityContext array{
 *     type: string,
 *     character_id: int|null,
 *     character: Character|null,
 *     name: string,
 *     snapshot: array<string, mixed>
 * }
 * @phpstan-type MagicEffectData array{
 *     effect_type: string,
 *     effect_amount: int,
 *     was_applied: bool,
 *     applied_le_delta: int|null,
 *     resulting_le_current: int|null,
 *     resulting_le_max: int|null,
 *     applied_ae_delta: int|null,
 *     resulting_ae_current: int|null,
 *     resulting_ae_max: int|null,
 *     target_attribute_key: string|null,
 *     applied_attribute_delta: int|null,
 *     resulting_attribute_current: int|null,
 *     resulting_attribute_max: int|null
 * }
 */
class MagicService
{
    public const EFFECT_LE_DAMAGE = MagicEffectApplier::EFFECT_LE_DAMAGE;

    public const EFFECT_LE_HEAL = MagicEffectApplier::EFFECT_LE_HEAL;

    public const EFFECT_AE_DAMAGE = MagicEffectApplier::EFFECT_AE_DAMAGE;

    public const EFFECT_ATTRIBUTE_DELTA = MagicEffectApplier::EFFECT_ATTRIBUTE_DELTA;

    public const EFFECT_NARRATIVE = MagicEffectApplier::EFFECT_NARRATIVE;

    private readonly ProbeRoller $probeRoller;

    private readonly MagicSnapshotBuilder $snapshotBuilder;

    private readonly MagicParticipantResolver $participantResolver;

    private readonly MagicEffectApplier $effectApplier;

    public function __construct(
        ProbeRoller $probeRoller,
        CampaignParticipantResolver $campaignParticipantResolver,
        ?MagicSnapshotBuilder $snapshotBuilder = null,
        ?MagicParticipantResolver $participantResolver = null,
        ?MagicEffectApplier $effectApplier = null,
    ) {
        $resolvedSnapshotBuilder = $snapshotBuilder ?? new MagicSnapshotBuilder();

        $this->probeRoller = $probeRoller;
        $this->snapshotBuilder = $resolvedSnapshotBuilder;
        $this->participantResolver = $participantResolver
            ?? new MagicParticipantResolver($campaignParticipantResolver, $resolvedSnapshotBuilder);
        $this->effectApplier = $effectApplier
            ?? new MagicEffectApplier($resolvedSnapshotBuilder);
    }

    /**
     * @throws MagicInvariantViolationException
     */
    public function resolveSingleAction(MagicActionInput $input): MagicActionResult
    {
        $campaign = $input->campaign;
        $scene = $input->scene;

        $this->participantResolver->assertSceneCampaignScope($campaign, $scene);

        $participantUserIds = $this->participantResolver->participantUserIds($campaign);
        $actorContext = $this->participantResolver->resolveActorContext($input->actor, $campaign, $participantUserIds);
        $targetContext = $this->participantResolver->resolveTargetContext($input->target, $campaign, $participantUserIds);

        $spellName = $this->normalizeSpellName($input->spellName);
        $spellTargetValue = $this->clampInt($input->spellTargetValue, 0, 100);
        $spellModifier = $this->clampInt($input->spellModifier, -100, 100);
        $aeCost = $this->clampInt($input->aeCost, 0, 999);

        $defenseLabel = $this->trimNullable($input->defenseLabel);
        $defenseTargetValue = $input->defenseTargetValue !== null
            ? $this->clampInt($input->defenseTargetValue, 0, 100)
            : null;
        $defenseModifier = $this->clampInt($input->defenseModifier, -100, 100);

        $effectType = $this->effectApplier->normalizeEffectType($input->effectType);
        $targetAttributeKey = $this->effectApplier->normalizeTargetAttributeKey(
            effectType: $effectType,
            targetAttributeKey: $input->targetAttributeKey,
            targetCharacter: $targetContext['character'],
        );
        $effectAmount = $this->effectApplier->normalizeEffectAmount($effectType, $input->effectAmount);

        $intentText = $this->trimNullable($input->intentText);
        $resolutionNote = $this->trimNullable($input->resolutionNote);

        $hasCharacterMutation = $actorContext['character'] instanceof Character
            || $targetContext['character'] instanceof Character;

        if ($hasCharacterMutation) {
            return DB::transaction(function () use (
                $campaign,
                $participantUserIds,
                $actorContext,
                $targetContext,
                $spellName,
                $spellTargetValue,
                $input,
                $spellModifier,
                $aeCost,
                $defenseLabel,
                $defenseTargetValue,
                $defenseModifier,
                $effectType,
                $effectAmount,
                $targetAttributeKey,
                $intentText,
                $resolutionNote,
            ): MagicActionResult {
                $lockedCharacters = $this->participantResolver->lockCharactersForAction(
                    $actorContext['character'],
                    $targetContext['character'],
                );

                $resolvedActor = $this->participantResolver->resolveLockedActorContext(
                    $actorContext,
                    $campaign,
                    $participantUserIds,
                    $lockedCharacters,
                );
                $resolvedTarget = $this->participantResolver->resolveLockedTargetContext(
                    $targetContext,
                    $campaign,
                    $participantUserIds,
                    $lockedCharacters,
                );

                return $this->resolveWithContexts(
                    actorContext: $resolvedActor,
                    targetContext: $resolvedTarget,
                    spellName: $spellName,
                    spellTargetValue: $spellTargetValue,
                    spellRollMode: $input->spellRollMode,
                    spellModifier: $spellModifier,
                    aeCost: $aeCost,
                    defenseLabel: $defenseLabel,
                    defenseTargetValue: $defenseTargetValue,
                    defenseRollMode: $input->defenseRollMode,
                    defenseModifier: $defenseModifier,
                    effectType: $effectType,
                    effectAmount: $effectAmount,
                    targetAttributeKey: $targetAttributeKey,
                    intentText: $intentText,
                    resolutionNote: $resolutionNote,
                );
            });
        }

        return $this->resolveWithContexts(
            actorContext: $actorContext,
            targetContext: $targetContext,
            spellName: $spellName,
            spellTargetValue: $spellTargetValue,
            spellRollMode: $input->spellRollMode,
            spellModifier: $spellModifier,
            aeCost: $aeCost,
            defenseLabel: $defenseLabel,
            defenseTargetValue: $defenseTargetValue,
            defenseRollMode: $input->defenseRollMode,
            defenseModifier: $defenseModifier,
            effectType: $effectType,
            effectAmount: $effectAmount,
            targetAttributeKey: $targetAttributeKey,
            intentText: $intentText,
            resolutionNote: $resolutionNote,
        );
    }

    /**
     * @param  MagicEntityContext  $actorContext
     * @param  MagicEntityContext  $targetContext
     */
    private function resolveWithContexts(
        array $actorContext,
        array $targetContext,
        string $spellName,
        int $spellTargetValue,
        string $spellRollMode,
        int $spellModifier,
        int $aeCost,
        ?string $defenseLabel,
        ?int $defenseTargetValue,
        string $defenseRollMode,
        int $defenseModifier,
        string $effectType,
        int $effectAmount,
        ?string $targetAttributeKey,
        ?string $intentText,
        ?string $resolutionNote,
    ): MagicActionResult {
        $actorSnapshotBefore = $actorContext['snapshot'];
        $targetSnapshotBefore = $targetContext['snapshot'];

        $appliedActorAeDelta = 0;
        $resultingActorAeCurrent = $this->snapshotBuilder->snapshotInt($actorSnapshotBefore, 'ae_current');
        $resultingActorAeMax = $this->snapshotBuilder->snapshotInt($actorSnapshotBefore, 'ae_max');

        if ($actorContext['type'] === MagicActor::TYPE_CHARACTER && $actorContext['character'] instanceof Character) {
            [$appliedActorAeDelta, $resultingActorAeCurrent, $resultingActorAeMax] = $this->applyActorAeCost(
                $actorContext['character'],
                $aeCost,
            );
        } elseif ($actorContext['type'] === MagicActor::TYPE_NPC) {
            [$actorSnapshotAfter, $appliedActorAeDelta, $resultingActorAeCurrent, $resultingActorAeMax] = $this->resolveNpcAeCost(
                $actorSnapshotBefore,
                $aeCost,
            );
            $actorSnapshotBefore = $actorContext['snapshot'];
            $actorContext['snapshot'] = $actorSnapshotAfter;
        }

        $spellRoll = $this->probeRoller->roll($spellRollMode, $spellModifier);
        $spellSuccess = (int) $spellRoll['total'] <= $spellTargetValue;

        $defenseAttempted = $spellSuccess && $defenseTargetValue !== null;
        $defenseRoll = null;
        $defenseSuccess = null;

        if ($defenseAttempted) {
            $defenseRoll = $this->probeRoller->roll($defenseRollMode, $defenseModifier);
            $defenseSuccess = (int) $defenseRoll['total'] <= (int) $defenseTargetValue;
        }

        $effectApplied = $spellSuccess && $defenseSuccess !== true;

        $targetSnapshotAfter = $targetSnapshotBefore;
        $effectData = $this->effectApplier->emptyEffectData($effectType, $effectAmount, $targetAttributeKey, $effectApplied);

        if ($effectApplied) {
            $appliedEffect = $this->effectApplier->applyEffect(
                targetContext: $targetContext,
                targetSnapshotBefore: $targetSnapshotBefore,
                effectType: $effectType,
                effectAmount: $effectAmount,
                targetAttributeKey: $targetAttributeKey,
            );
            $targetSnapshotAfter = $this->snapshotBuilder->normalizeSnapshot($appliedEffect['target_snapshot_after']);
            $effectData = $appliedEffect['effect'];
        }

        $dirtyCharacters = [];

        if ($actorContext['character'] instanceof Character && $actorContext['character']->isDirty()) {
            $dirtyCharacters[(int) $actorContext['character']->id] = $actorContext['character'];
        }

        if ($targetContext['character'] instanceof Character && $targetContext['character']->isDirty()) {
            $dirtyCharacters[(int) $targetContext['character']->id] = $targetContext['character'];
        }

        foreach ($dirtyCharacters as $character) {
            $character->save();
        }

        $actorSnapshotAfter = $actorContext['character'] instanceof Character
            ? $this->snapshotBuilder->buildCharacterSnapshot($actorContext['character'], $actorContext['snapshot'])
            : $actorContext['snapshot'];

        if ($targetContext['character'] instanceof Character) {
            $targetSnapshotAfter = $this->snapshotBuilder->buildCharacterSnapshot($targetContext['character'], $targetContext['snapshot']);
        }

        $spellData = [
            'target_value' => $spellTargetValue,
            'roll_mode' => (string) $spellRoll['mode'],
            'rolls' => array_values((array) $spellRoll['rolls']),
            'kept_roll' => (int) $spellRoll['kept_roll'],
            'modifier' => (int) $spellRoll['modifier'],
            'total' => (int) $spellRoll['total'],
            'is_success' => $spellSuccess,
            'critical_success' => (bool) $spellRoll['critical_success'],
            'critical_failure' => (bool) $spellRoll['critical_failure'],
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

        $aeCostData = [
            'requested_ae_cost' => $aeCost,
            'applied_ae_delta' => $appliedActorAeDelta,
            'resulting_actor_ae_current' => $resultingActorAeCurrent,
            'resulting_actor_ae_max' => $resultingActorAeMax,
        ];

        $snapshotsData = [
            'actor_snapshot_before' => $actorSnapshotBefore,
            'actor_snapshot_after' => $actorSnapshotAfter,
            'target_snapshot_before' => $targetSnapshotBefore,
            'target_snapshot_after' => $targetSnapshotAfter,
        ];

        $summary = $this->buildSummary(
            actorName: $actorContext['name'],
            targetName: $targetContext['name'],
            spellName: $spellName,
            spellSuccess: $spellSuccess,
            defenseSuccess: $defenseSuccess,
            effectData: $effectData,
        );

        $logLines = $this->buildLogLines(
            actorName: $actorContext['name'],
            targetName: $targetContext['name'],
            spellName: $spellName,
            spellData: $spellData,
            defenseData: $defenseData,
            aeCostData: $aeCostData,
            effectData: $effectData,
            intentText: $intentText,
            resolutionNote: $resolutionNote,
        );

        return new MagicActionResult(
            actorType: $actorContext['type'],
            actorCharacterId: $actorContext['character_id'],
            actorName: $actorContext['name'],
            targetType: $targetContext['type'],
            targetCharacterId: $targetContext['character_id'],
            targetName: $targetContext['name'],
            spellName: $spellName,
            aeCost: $aeCostData,
            spellRoll: $spellData,
            defense: $defenseData,
            effect: $effectData,
            snapshots: $snapshotsData,
            summary: $summary,
            logLines: $logLines,
        );
    }

    /**
     * @return array{0: int, 1: int|null, 2: int|null}
     *
     * @throws MagicInvariantViolationException
     */
    private function applyActorAeCost(Character $character, int $requestedCost): array
    {
        $rawMax = $character->ae_max;
        $rawCurrent = $character->ae_current;

        $maxValue = max((int) ($rawMax ?? $rawCurrent ?? 0), 0);
        $currentValue = $this->clampInt((int) ($rawCurrent ?? $maxValue), 0, $maxValue);

        if ($requestedCost > $currentValue) {
            throw MagicInvariantViolationException::insufficientAstralEnergy(
                characterId: (int) $character->id,
                requestedCost: $requestedCost,
                availableCurrent: $currentValue,
            );
        }

        $resultingValue = $this->clampInt($currentValue - $requestedCost, 0, $maxValue);
        $appliedDelta = $resultingValue - $currentValue;

        $rawCurrentInt = $rawCurrent === null ? null : (int) $rawCurrent;
        $needsNormalization = $rawCurrentInt !== null && $rawCurrentInt !== $currentValue;

        if ($appliedDelta !== 0 || $needsNormalization) {
            /** @var int<0, max> $normalizedResultingValue */
            $normalizedResultingValue = max(0, $resultingValue);
            $character->ae_current = $normalizedResultingValue;
        }

        return [$appliedDelta, $resultingValue, $maxValue];
    }

    /**
     * @param  array<string, mixed>  $actorSnapshotBefore
     * @return array{0: array<string, mixed>, 1: int, 2: int|null, 3: int|null}
     */
    private function resolveNpcAeCost(array $actorSnapshotBefore, int $requestedCost): array
    {
        $rawCurrent = $this->snapshotBuilder->snapshotInt($actorSnapshotBefore, 'ae_current');
        $rawMax = $this->snapshotBuilder->snapshotInt($actorSnapshotBefore, 'ae_max');

        if ($rawCurrent === null && $rawMax === null) {
            return [$actorSnapshotBefore, 0, null, null];
        }

        $maxValue = max($rawMax ?? $rawCurrent ?? 0, 0);
        $currentValue = $this->clampInt((int) ($rawCurrent ?? $maxValue), 0, $maxValue);
        $resultingValue = $this->clampInt($currentValue - $requestedCost, 0, $maxValue);
        $appliedDelta = $resultingValue - $currentValue;

        $snapshotAfter = $actorSnapshotBefore;
        $snapshotAfter['ae_current'] = $resultingValue;

        if ($rawMax !== null) {
            $snapshotAfter['ae_max'] = $maxValue;
        }

        return [$snapshotAfter, $appliedDelta, $resultingValue, $rawMax !== null ? $maxValue : null];
    }

    private function normalizeSpellName(string $spellName): string
    {
        $trimmed = trim($spellName);

        return $trimmed === '' ? 'Unbenannter Zauber' : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $spellData
     * @param  array<string, mixed>  $defenseData
     * @param  array<string, mixed>  $aeCostData
     * @param  MagicEffectData  $effectData
     * @return list<string>
     */
    private function buildLogLines(
        string $actorName,
        string $targetName,
        string $spellName,
        array $spellData,
        array $defenseData,
        array $aeCostData,
        array $effectData,
        ?string $intentText,
        ?string $resolutionNote,
    ): array {
        $lines = [];

        if ($intentText !== null && $intentText !== '') {
            $lines[] = 'Absicht: '.$intentText;
        }

        $lines[] = sprintf(
            'Zauber %s -> %s [%s]: Wurf %d %+d = %d gegen %d (%s).',
            $actorName,
            $targetName,
            $spellName,
            (int) ($spellData['kept_roll'] ?? 0),
            (int) ($spellData['modifier'] ?? 0),
            (int) ($spellData['total'] ?? 0),
            (int) ($spellData['target_value'] ?? 0),
            (bool) ($spellData['is_success'] ?? false) ? 'Erfolg' : 'Misserfolg'
        );

        $lines[] = sprintf(
            'AE-Kosten: %d (Delta %d).',
            (int) ($aeCostData['requested_ae_cost'] ?? 0),
            (int) ($aeCostData['applied_ae_delta'] ?? 0)
        );

        if ((bool) ($defenseData['attempted'] ?? false)) {
            $lines[] = sprintf(
                'Magieabwehr%s: Wurf %d %+d = %d gegen %d (%s).',
                $defenseData['label'] !== null && $defenseData['label'] !== ''
                    ? ' ['.$defenseData['label'].']'
                    : '',
                (int) ($defenseData['kept_roll'] ?? 0),
                (int) ($defenseData['modifier'] ?? 0),
                (int) ($defenseData['total'] ?? 0),
                (int) ($defenseData['target_value'] ?? 0),
                (bool) ($defenseData['is_success'] ?? false) ? 'Erfolg' : 'Misserfolg'
            );
        }

        $lines[] = $this->effectLine($effectData);

        if ($resolutionNote !== null && $resolutionNote !== '') {
            $lines[] = 'SL-Notiz: '.$resolutionNote;
        }

        return $lines;
    }

    /**
     * @param  MagicEffectData  $effectData
     */
    private function effectLine(array $effectData): string
    {
        if (! (bool) $effectData['was_applied']) {
            return 'Wirkung: Keine Wirkung angewendet.';
        }

        return match ((string) $effectData['effect_type']) {
            self::EFFECT_LE_DAMAGE => sprintf(
                'Wirkung: LE-Schaden %d (Delta %d).',
                (int) $effectData['effect_amount'],
                (int) ($effectData['applied_le_delta'] ?? 0)
            ),
            self::EFFECT_LE_HEAL => sprintf(
                'Wirkung: LE-Heilung %d (Delta %+d).',
                (int) $effectData['effect_amount'],
                (int) ($effectData['applied_le_delta'] ?? 0)
            ),
            self::EFFECT_AE_DAMAGE => sprintf(
                'Wirkung: AE-Verlust %d (Delta %d).',
                (int) $effectData['effect_amount'],
                (int) ($effectData['applied_ae_delta'] ?? 0)
            ),
            self::EFFECT_ATTRIBUTE_DELTA => sprintf(
                'Wirkung: %s %+d (Delta %+d).',
                (string) ($effectData['target_attribute_key'] ?? 'attribut'),
                (int) $effectData['effect_amount'],
                (int) ($effectData['applied_attribute_delta'] ?? 0)
            ),
            default => 'Wirkung: Rein erzaehlerischer Effekt.',
        };
    }

    /**
     * @param  MagicEffectData  $effectData
     */
    private function buildSummary(
        string $actorName,
        string $targetName,
        string $spellName,
        bool $spellSuccess,
        ?bool $defenseSuccess,
        array $effectData,
    ): string {
        if (! $spellSuccess) {
            return sprintf('%s wirkt %s auf %s, aber der Zauber misslingt.', $actorName, $spellName, $targetName);
        }

        if ($defenseSuccess === true) {
            return sprintf('%s wirkt %s auf %s, aber die Magieabwehr verhindert die Wirkung.', $actorName, $spellName, $targetName);
        }

        if (! (bool) $effectData['was_applied']) {
            return sprintf('%s wirkt %s auf %s ohne regeltechnische Wirkung.', $actorName, $spellName, $targetName);
        }

        return match ((string) $effectData['effect_type']) {
            self::EFFECT_LE_DAMAGE => sprintf(
                '%s wirkt %s auf %s: %d LE-Schaden.',
                $actorName,
                $spellName,
                $targetName,
                max(0, -(int) ($effectData['applied_le_delta'] ?? 0))
            ),
            self::EFFECT_LE_HEAL => sprintf(
                '%s wirkt %s auf %s: %d LE-Heilung.',
                $actorName,
                $spellName,
                $targetName,
                max(0, (int) ($effectData['applied_le_delta'] ?? 0))
            ),
            self::EFFECT_AE_DAMAGE => sprintf(
                '%s wirkt %s auf %s: %d AE-Verlust.',
                $actorName,
                $spellName,
                $targetName,
                max(0, -(int) ($effectData['applied_ae_delta'] ?? 0))
            ),
            self::EFFECT_ATTRIBUTE_DELTA => sprintf(
                '%s wirkt %s auf %s: %s %+d.',
                $actorName,
                $spellName,
                $targetName,
                (string) ($effectData['target_attribute_key'] ?? 'attribut'),
                (int) ($effectData['applied_attribute_delta'] ?? 0)
            ),
            default => sprintf('%s wirkt %s auf %s mit erzaehlerischer Wirkung.', $actorName, $spellName, $targetName),
        };
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
}
