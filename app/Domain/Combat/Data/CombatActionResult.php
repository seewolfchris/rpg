<?php

declare(strict_types=1);

namespace App\Domain\Combat\Data;

/**
 * @phpstan-type CombatAttackResult array{
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
 * @phpstan-type CombatDefenseResult array{
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
 * @phpstan-type CombatOutcomeResult array{
 *     attack_hit: bool,
 *     defense_prevented_hit: bool,
 *     raw_damage: int,
 *     armor_protection: int,
 *     effective_damage: int,
 *     applied_le_delta: int,
 *     resulting_le_current: int|null,
 *     resulting_le_max: int|null
 * }
 * @phpstan-type CombatSnapshotResult array{
 *     actor_snapshot: array<string, mixed>,
 *     target_snapshot_before: array<string, mixed>,
 *     target_snapshot_after: array<string, mixed>
 * }
 */
final readonly class CombatActionResult
{
    /**
     * @param  CombatAttackResult  $attack
     * @param  CombatDefenseResult  $defense
     * @param  CombatOutcomeResult  $outcome
     * @param  CombatSnapshotResult  $snapshots
     * @param  list<string>  $logLines
     */
    public function __construct(
        public string $actorType,
        public ?int $actorCharacterId,
        public string $actorName,
        public string $targetType,
        public ?int $targetCharacterId,
        public string $targetName,
        public ?string $weaponName,
        public array $attack,
        public array $defense,
        public array $outcome,
        public array $snapshots,
        public string $summary,
        public array $logLines,
    ) {}

    /**
     * @return array{
     *     actor: array{type: string, character_id: int|null, name: string},
     *     target: array{type: string, character_id: int|null, name: string},
     *     weapon_name: string|null,
     *     attack: CombatAttackResult,
     *     defense: CombatDefenseResult,
     *     outcome: CombatOutcomeResult,
     *     snapshots: CombatSnapshotResult,
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
            'weapon_name' => $this->weaponName,
            'attack' => $this->attack,
            'defense' => $this->defense,
            'outcome' => $this->outcome,
            'snapshots' => $this->snapshots,
            'summary' => $this->summary,
            'log_lines' => $this->logLines,
        ];
    }
}
