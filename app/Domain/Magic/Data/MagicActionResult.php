<?php

declare(strict_types=1);

namespace App\Domain\Magic\Data;

/**
 * @phpstan-type MagicSpellRollResult array{
 *     target_value: int,
 *     roll_mode: string,
 *     rolls: list<int>,
 *     kept_roll: int,
 *     modifier: int,
 *     total: int,
 *     is_success: bool,
 *     critical_success: bool,
 *     critical_failure: bool
 * }
 * @phpstan-type MagicDefenseResult array{
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
 * }
 * @phpstan-type MagicAeCostResult array{
 *     requested_ae_cost: int,
 *     applied_ae_delta: int,
 *     resulting_actor_ae_current: int|null,
 *     resulting_actor_ae_max: int|null
 * }
 * @phpstan-type MagicEffectResult array{
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
 * @phpstan-type MagicSnapshotResult array{
 *     actor_snapshot_before: array<string, mixed>,
 *     actor_snapshot_after: array<string, mixed>,
 *     target_snapshot_before: array<string, mixed>,
 *     target_snapshot_after: array<string, mixed>
 * }
 */
final readonly class MagicActionResult
{
    /**
     * @param  MagicAeCostResult  $aeCost
     * @param  MagicSpellRollResult  $spellRoll
     * @param  MagicDefenseResult  $defense
     * @param  MagicEffectResult  $effect
     * @param  MagicSnapshotResult  $snapshots
     * @param  list<string>  $logLines
     */
    public function __construct(
        public string $actorType,
        public ?int $actorCharacterId,
        public string $actorName,
        public string $targetType,
        public ?int $targetCharacterId,
        public string $targetName,
        public string $spellName,
        public array $aeCost,
        public array $spellRoll,
        public array $defense,
        public array $effect,
        public array $snapshots,
        public string $summary,
        public array $logLines,
    ) {}

    /**
     * @return array{
     *     actor: array{type: string, character_id: int|null, name: string},
     *     target: array{type: string, character_id: int|null, name: string},
     *     spell_name: string,
     *     ae_cost: MagicAeCostResult,
     *     spell_roll: MagicSpellRollResult,
     *     defense: MagicDefenseResult,
     *     effect: MagicEffectResult,
     *     snapshots: MagicSnapshotResult,
     *     summary: string,
     *     log_lines: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'actor' => [
                'type' => $this->actorType,
                'character_id' => $this->actorCharacterId,
                'name' => $this->actorName,
            ],
            'target' => [
                'type' => $this->targetType,
                'character_id' => $this->targetCharacterId,
                'name' => $this->targetName,
            ],
            'spell_name' => $this->spellName,
            'ae_cost' => $this->aeCost,
            'spell_roll' => $this->spellRoll,
            'defense' => $this->defense,
            'effect' => $this->effect,
            'snapshots' => $this->snapshots,
            'summary' => $this->summary,
            'log_lines' => $this->logLines,
        ];
    }
}
