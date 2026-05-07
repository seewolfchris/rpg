<?php

declare(strict_types=1);

namespace App\Domain\SceneConflict;

use App\Domain\Combat\Data\CombatActionResult;
use App\Domain\Combat\Data\CombatPhaseResolutionResult;
use App\Domain\Magic\Data\MagicActionResult;
use App\Domain\Magic\MagicService;
use App\Models\CombatPhase;
use App\Models\SceneConflictActor;

class SceneConflictActorResultApplier
{
    public function applyCombatSingleAction(
        ?SceneConflictActor $targetConflictActor,
        CombatActionResult $result,
    ): void {
        $targetActor = $this->resolveTargetActor(
            fallback: $targetConflictActor,
            snapshot: $result->snapshots['target_snapshot_after'] ?? [],
            outcomeCurrent: $result->outcome['resulting_le_current'] ?? null,
            outcomeMax: $result->outcome['resulting_le_max'] ?? null,
        );

        if (! $targetActor instanceof SceneConflictActor || ! $targetActor->isNpc()) {
            return;
        }

        $nextLeCurrent = $this->nullableInt($result->outcome['resulting_le_current'] ?? null)
            ?? $this->snapshotInt($result->snapshots['target_snapshot_after'] ?? [], 'le_current');

        $nextLeMax = $this->nullableInt($result->outcome['resulting_le_max'] ?? null)
            ?? $this->snapshotInt($result->snapshots['target_snapshot_after'] ?? [], 'le_max');

        $this->applyNpcLe($targetActor, $nextLeCurrent, $nextLeMax);
    }

    public function applyMagicSingleAction(
        ?SceneConflictActor $actorConflictActor,
        ?SceneConflictActor $targetConflictActor,
        MagicActionResult $result,
    ): void {
        $actorSnapshotAfter = is_array($result->snapshots['actor_snapshot_after'] ?? null)
            ? $result->snapshots['actor_snapshot_after']
            : [];
        $targetSnapshotAfter = is_array($result->snapshots['target_snapshot_after'] ?? null)
            ? $result->snapshots['target_snapshot_after']
            : [];

        $actor = $this->resolveActorFromSnapshot($actorConflictActor, $actorSnapshotAfter);
        if ($actor instanceof SceneConflictActor && $actor->isNpc()) {
            $resultingAeCurrent = $this->nullableInt($result->aeCost['resulting_actor_ae_current'] ?? null)
                ?? $this->snapshotInt($actorSnapshotAfter, 'ae_current');
            $resultingAeMax = $this->nullableInt($result->aeCost['resulting_actor_ae_max'] ?? null)
                ?? $this->snapshotInt($actorSnapshotAfter, 'ae_max');

            $this->applyNpcAe($actor, $resultingAeCurrent, $resultingAeMax);
        }

        $target = $this->resolveActorFromSnapshot($targetConflictActor, $targetSnapshotAfter);
        if (! $target instanceof SceneConflictActor || ! $target->isNpc()) {
            return;
        }

        $effectType = (string) ($result->effect['effect_type'] ?? '');
        if ($effectType === MagicService::EFFECT_LE_DAMAGE || $effectType === MagicService::EFFECT_LE_HEAL) {
            $resultingLeCurrent = $this->nullableInt($result->effect['resulting_le_current'] ?? null)
                ?? $this->snapshotInt($targetSnapshotAfter, 'le_current');
            $resultingLeMax = $this->nullableInt($result->effect['resulting_le_max'] ?? null)
                ?? $this->snapshotInt($targetSnapshotAfter, 'le_max');
            $this->applyNpcLe($target, $resultingLeCurrent, $resultingLeMax);

            return;
        }

        if ($effectType === MagicService::EFFECT_AE_DAMAGE) {
            $resultingAeCurrent = $this->nullableInt($result->effect['resulting_ae_current'] ?? null)
                ?? $this->snapshotInt($targetSnapshotAfter, 'ae_current');
            $resultingAeMax = $this->nullableInt($result->effect['resulting_ae_max'] ?? null)
                ?? $this->snapshotInt($targetSnapshotAfter, 'ae_max');
            $this->applyNpcAe($target, $resultingAeCurrent, $resultingAeMax);
        }
    }

    public function applyCombatPhaseResolution(
        CombatPhase $phase,
        CombatPhaseResolutionResult $resolution,
    ): void {
        foreach ($resolution->results as $resultItem) {
            $result = is_array($resultItem['result'] ?? null) ? $resultItem['result'] : [];
            $snapshots = is_array($result['snapshots'] ?? null) ? $result['snapshots'] : [];
            $targetSnapshotAfter = is_array($snapshots['target_snapshot_after'] ?? null)
                ? $snapshots['target_snapshot_after']
                : [];

            $conflictActorId = $this->snapshotInt($targetSnapshotAfter, 'scene_conflict_actor_id');
            if ($conflictActorId === null || $conflictActorId <= 0) {
                continue;
            }

            /** @var SceneConflictActor|null $targetActor */
            $targetActor = SceneConflictActor::query()
                ->whereKey($conflictActorId)
                ->where('campaign_id', (int) $phase->campaign_id)
                ->where('scene_id', (int) $phase->scene_id)
                ->first();

            if (! $targetActor instanceof SceneConflictActor || ! $targetActor->isNpc()) {
                continue;
            }

            $outcome = is_array($result['outcome'] ?? null) ? $result['outcome'] : [];
            $nextLeCurrent = $this->nullableInt($outcome['resulting_le_current'] ?? null)
                ?? $this->snapshotInt($targetSnapshotAfter, 'le_current');
            $nextLeMax = $this->nullableInt($outcome['resulting_le_max'] ?? null)
                ?? $this->snapshotInt($targetSnapshotAfter, 'le_max');

            $this->applyNpcLe($targetActor, $nextLeCurrent, $nextLeMax);
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function resolveTargetActor(
        ?SceneConflictActor $fallback,
        array $snapshot,
        mixed $outcomeCurrent,
        mixed $outcomeMax,
    ): ?SceneConflictActor {
        $resolved = $this->resolveActorFromSnapshot($fallback, $snapshot);
        if (! $resolved instanceof SceneConflictActor) {
            return null;
        }

        if (
            $this->nullableInt($outcomeCurrent) === null
            && $this->nullableInt($outcomeMax) === null
            && $this->snapshotInt($snapshot, 'le_current') === null
            && $this->snapshotInt($snapshot, 'le_max') === null
        ) {
            return null;
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function resolveActorFromSnapshot(?SceneConflictActor $fallback, array $snapshot): ?SceneConflictActor
    {
        $conflictActorId = $this->snapshotInt($snapshot, 'scene_conflict_actor_id');

        if ($conflictActorId !== null && $conflictActorId > 0) {
            /** @var SceneConflictActor|null $actor */
            $actor = SceneConflictActor::query()->find($conflictActorId);
            if ($actor instanceof SceneConflictActor) {
                return $actor;
            }
        }

        return $fallback;
    }

    private function applyNpcLe(SceneConflictActor $actor, ?int $nextCurrent, ?int $nextMax): void
    {
        $attributes = [];

        if ($nextMax !== null) {
            $attributes['le_max'] = max(0, $nextMax);
        }
        if ($nextCurrent !== null) {
            $safeCurrent = max(0, $nextCurrent);
            if (array_key_exists('le_max', $attributes)) {
                $safeCurrent = min($safeCurrent, (int) $attributes['le_max']);
            } elseif ($actor->le_max !== null) {
                $safeCurrent = min($safeCurrent, max(0, (int) $actor->le_max));
            }
            $attributes['le_current'] = $safeCurrent;
        }

        if ($attributes === []) {
            return;
        }

        $actor->fill($attributes);
        $actor->save();
    }

    private function applyNpcAe(SceneConflictActor $actor, ?int $nextCurrent, ?int $nextMax): void
    {
        $attributes = [];

        if ($nextMax !== null) {
            $attributes['ae_max'] = max(0, $nextMax);
        }
        if ($nextCurrent !== null) {
            $safeCurrent = max(0, $nextCurrent);
            if (array_key_exists('ae_max', $attributes)) {
                $safeCurrent = min($safeCurrent, (int) $attributes['ae_max']);
            } elseif ($actor->ae_max !== null) {
                $safeCurrent = min($safeCurrent, max(0, (int) $actor->ae_max));
            }
            $attributes['ae_current'] = $safeCurrent;
        }

        if ($attributes === []) {
            return;
        }

        $actor->fill($attributes);
        $actor->save();
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_int($value) || is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function snapshotInt(array $snapshot, string $key): ?int
    {
        if (! array_key_exists($key, $snapshot)) {
            return null;
        }

        return $this->nullableInt($snapshot[$key]);
    }
}
