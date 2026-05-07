<?php

declare(strict_types=1);

namespace App\Domain\SceneConflict;

use App\Domain\Combat\Data\CombatActor;
use App\Domain\Combat\Data\CombatTarget;
use App\Domain\Combat\Exceptions\CombatInvariantViolationException;
use App\Domain\Magic\Data\MagicActor;
use App\Domain\Magic\Data\MagicTarget;
use App\Domain\Magic\Exceptions\MagicInvariantViolationException;
use App\Models\Character;
use App\Models\Scene;
use App\Models\SceneConflictActor;

/**
 * @phpstan-type CombatMappedEntity array{
 *     conflict_actor: SceneConflictActor|null,
 *     actor: CombatActor|CombatTarget,
 *     defaults: array{
 *         attack_target_value: int|null,
 *         damage: int|null,
 *         defense_target_value: int|null,
 *         armor_protection: int|null,
 *         weapon_name: string|null
 *     }
 * }
 * @phpstan-type MagicMappedEntity array{
 *     conflict_actor: SceneConflictActor|null,
 *     actor: MagicActor|MagicTarget,
 *     defaults: array{
 *         spell_target_value: int|null,
 *         defense_target_value: int|null
 *     }
 * }
 */
class SceneConflictActorInputMapper
{
    /**
     * @param  array<string, mixed>  $data
     * @return CombatMappedEntity
     *
     * @throws CombatInvariantViolationException
     */
    public function mapCombatActor(Scene $scene, array $data): array
    {
        $conflictActor = $this->resolveConflictActorForCombat(
            scene: $scene,
            conflictActorId: $this->nullableInt($data['actor_conflict_actor_id'] ?? null),
            field: 'actor_conflict_actor_id',
        );

        if ($conflictActor instanceof SceneConflictActor) {
            $attackDefault = $this->nullableInt($conflictActor->attack_value);
            $damageDefault = $this->nullableInt($conflictActor->damage_value);
            $weaponNameDefault = null;

            if ($conflictActor->isCharacter() && $conflictActor->character instanceof Character) {
                $attackDefault = $conflictActor->character->activeWeaponAttackValue();
                $damageDefault = $conflictActor->character->activeWeaponEffectValue();
                $weaponNameDefault = $conflictActor->character->activeWeaponName();
            }

            return [
                'conflict_actor' => $conflictActor,
                'actor' => $this->combatActorFromConflictActor($conflictActor, true),
                'defaults' => [
                    'attack_target_value' => $attackDefault,
                    'damage' => $damageDefault,
                    'defense_target_value' => null,
                    'armor_protection' => null,
                    'weapon_name' => $weaponNameDefault,
                ],
            ];
        }

        return [
            'conflict_actor' => null,
            'actor' => $this->manualCombatActor($data),
            'defaults' => [
                'attack_target_value' => null,
                'damage' => null,
                'defense_target_value' => null,
                'armor_protection' => null,
                'weapon_name' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return CombatMappedEntity
     *
     * @throws CombatInvariantViolationException
     */
    public function mapCombatTarget(Scene $scene, array $data): array
    {
        $conflictActor = $this->resolveConflictActorForCombat(
            scene: $scene,
            conflictActorId: $this->nullableInt($data['target_conflict_actor_id'] ?? null),
            field: 'target_conflict_actor_id',
        );

        if ($conflictActor instanceof SceneConflictActor) {
            $defenseDefault = $this->nullableInt($conflictActor->defense_value);
            $armorDefault = $this->nullableInt($conflictActor->armor_protection);

            if ($conflictActor->isCharacter() && $conflictActor->character instanceof Character) {
                $defenseDefault = $conflictActor->character->activeWeaponDefenseValue();
                $armorDefault = $conflictActor->character->armorProtectionValue();
            }

            return [
                'conflict_actor' => $conflictActor,
                'actor' => $this->combatTargetFromConflictActor($conflictActor, false),
                'defaults' => [
                    'attack_target_value' => null,
                    'damage' => null,
                    'defense_target_value' => $defenseDefault,
                    'armor_protection' => $armorDefault,
                    'weapon_name' => null,
                ],
            ];
        }

        return [
            'conflict_actor' => null,
            'actor' => $this->manualCombatTarget($data),
            'defaults' => [
                'attack_target_value' => null,
                'damage' => null,
                'defense_target_value' => null,
                'armor_protection' => null,
                'weapon_name' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return MagicMappedEntity
     *
     * @throws MagicInvariantViolationException
     */
    public function mapMagicActor(Scene $scene, array $data): array
    {
        $conflictActor = $this->resolveConflictActorForMagic(
            scene: $scene,
            conflictActorId: $this->nullableInt($data['actor_conflict_actor_id'] ?? null),
            field: 'actor_conflict_actor_id',
        );

        if ($conflictActor instanceof SceneConflictActor) {
            return [
                'conflict_actor' => $conflictActor,
                'actor' => $this->magicActorFromConflictActor($conflictActor),
                'defaults' => [
                    'spell_target_value' => $this->nullableInt($conflictActor->spell_value),
                    'defense_target_value' => null,
                ],
            ];
        }

        return [
            'conflict_actor' => null,
            'actor' => $this->manualMagicActor($data),
            'defaults' => [
                'spell_target_value' => null,
                'defense_target_value' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return MagicMappedEntity
     *
     * @throws MagicInvariantViolationException
     */
    public function mapMagicTarget(Scene $scene, array $data): array
    {
        $conflictActor = $this->resolveConflictActorForMagic(
            scene: $scene,
            conflictActorId: $this->nullableInt($data['target_conflict_actor_id'] ?? null),
            field: 'target_conflict_actor_id',
        );

        if ($conflictActor instanceof SceneConflictActor) {
            return [
                'conflict_actor' => $conflictActor,
                'actor' => $this->magicTargetFromConflictActor($conflictActor),
                'defaults' => [
                    'spell_target_value' => null,
                    'defense_target_value' => $this->nullableInt($conflictActor->defense_value),
                ],
            ];
        }

        return [
            'conflict_actor' => null,
            'actor' => $this->manualMagicTarget($data),
            'defaults' => [
                'spell_target_value' => null,
                'defense_target_value' => null,
            ],
        ];
    }

    /**
     * @throws CombatInvariantViolationException
     */
    private function resolveConflictActorForCombat(Scene $scene, ?int $conflictActorId, string $field): ?SceneConflictActor
    {
        if ($conflictActorId === null || $conflictActorId <= 0) {
            return null;
        }

        /** @var SceneConflictActor|null $conflictActor */
        $conflictActor = SceneConflictActor::query()
            ->with('character')
            ->find($conflictActorId);

        if (! $conflictActor instanceof SceneConflictActor || (int) $conflictActor->scene_id !== (int) $scene->id) {
            throw new CombatInvariantViolationException(
                reason: 'conflict_actor_scope_mismatch',
                field: $field,
                message: 'Der ausgewählte Szenenbeteiligte gehört nicht zu dieser Szene.',
                context: [
                    'scene_id' => (int) $scene->id,
                    'scene_conflict_actor_id' => $conflictActorId,
                ],
            );
        }

        return $conflictActor;
    }

    /**
     * @throws MagicInvariantViolationException
     */
    private function resolveConflictActorForMagic(Scene $scene, ?int $conflictActorId, string $field): ?SceneConflictActor
    {
        if ($conflictActorId === null || $conflictActorId <= 0) {
            return null;
        }

        /** @var SceneConflictActor|null $conflictActor */
        $conflictActor = SceneConflictActor::query()
            ->with('character')
            ->find($conflictActorId);

        if (! $conflictActor instanceof SceneConflictActor || (int) $conflictActor->scene_id !== (int) $scene->id) {
            throw new MagicInvariantViolationException(
                reason: 'conflict_actor_scope_mismatch',
                field: $field,
                message: 'Der ausgewählte Szenenbeteiligte gehört nicht zu dieser Szene.',
                context: [
                    'scene_id' => (int) $scene->id,
                    'scene_conflict_actor_id' => $conflictActorId,
                ],
            );
        }

        return $conflictActor;
    }

    /**
     * @throws CombatInvariantViolationException
     */
    private function combatActorFromConflictActor(SceneConflictActor $conflictActor, bool $isActor): CombatActor
    {
        $snapshot = $this->combatSnapshotFromConflictActor($conflictActor);

        if ($conflictActor->isCharacter()) {
            if (! $conflictActor->character instanceof Character) {
                throw $isActor
                    ? CombatInvariantViolationException::actorCharacterMissing()
                    : CombatInvariantViolationException::targetCharacterMissing();
            }

            return CombatActor::character(
                character: $conflictActor->character,
                name: $conflictActor->displayName(),
                snapshot: $snapshot,
            );
        }

        return CombatActor::npc(
            name: $conflictActor->displayName(),
            snapshot: $snapshot,
        );
    }

    /**
     * @throws CombatInvariantViolationException
     */
    private function combatTargetFromConflictActor(SceneConflictActor $conflictActor, bool $isActor): CombatTarget
    {
        $snapshot = $this->combatSnapshotFromConflictActor($conflictActor);

        if ($conflictActor->isCharacter()) {
            if (! $conflictActor->character instanceof Character) {
                throw $isActor
                    ? CombatInvariantViolationException::actorCharacterMissing()
                    : CombatInvariantViolationException::targetCharacterMissing();
            }

            return CombatTarget::character(
                character: $conflictActor->character,
                name: $conflictActor->displayName(),
                snapshot: $snapshot,
            );
        }

        return CombatTarget::npc(
            name: $conflictActor->displayName(),
            snapshot: $snapshot,
        );
    }

    /**
     * @throws MagicInvariantViolationException
     */
    private function magicActorFromConflictActor(SceneConflictActor $conflictActor): MagicActor
    {
        $snapshot = $this->magicSnapshotFromConflictActor($conflictActor);

        if ($conflictActor->isCharacter()) {
            if (! $conflictActor->character instanceof Character) {
                throw MagicInvariantViolationException::actorCharacterMissing();
            }

            return MagicActor::character(
                character: $conflictActor->character,
                name: $conflictActor->displayName(),
                snapshot: $snapshot,
            );
        }

        return MagicActor::npc(
            name: $conflictActor->displayName(),
            snapshot: $snapshot,
        );
    }

    /**
     * @throws MagicInvariantViolationException
     */
    private function magicTargetFromConflictActor(SceneConflictActor $conflictActor): MagicTarget
    {
        $snapshot = $this->magicSnapshotFromConflictActor($conflictActor);

        if ($conflictActor->isCharacter()) {
            if (! $conflictActor->character instanceof Character) {
                throw MagicInvariantViolationException::targetCharacterMissing();
            }

            return MagicTarget::character(
                character: $conflictActor->character,
                name: $conflictActor->displayName(),
                snapshot: $snapshot,
            );
        }

        return MagicTarget::npc(
            name: $conflictActor->displayName(),
            snapshot: $snapshot,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws CombatInvariantViolationException
     */
    private function manualCombatActor(array $data): CombatActor
    {
        $type = (string) ($data['actor_type'] ?? '');

        if ($type === CombatActor::TYPE_CHARACTER) {
            $characterId = $this->nullableInt($data['actor_character_id'] ?? null) ?? 0;
            $character = Character::query()->find($characterId);

            if (! $character instanceof Character) {
                throw CombatInvariantViolationException::actorCharacterMissing();
            }

            return CombatActor::character($character);
        }

        $name = $this->nullableString($data['actor_name'] ?? null) ?? '';
        $snapshot = [
            'name' => $name,
        ];

        if (array_key_exists('actor_le_current', $data) && $data['actor_le_current'] !== null) {
            $snapshot['le_current'] = (int) $data['actor_le_current'];
        }
        if (array_key_exists('actor_le_max', $data) && $data['actor_le_max'] !== null) {
            $snapshot['le_max'] = (int) $data['actor_le_max'];
        }

        return CombatActor::npc($name, $snapshot);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws CombatInvariantViolationException
     */
    private function manualCombatTarget(array $data): CombatTarget
    {
        $type = (string) ($data['target_type'] ?? '');

        if ($type === CombatTarget::TYPE_CHARACTER) {
            $characterId = $this->nullableInt($data['target_character_id'] ?? null) ?? 0;
            $character = Character::query()->find($characterId);

            if (! $character instanceof Character) {
                throw CombatInvariantViolationException::targetCharacterMissing();
            }

            return CombatTarget::character($character);
        }

        $name = $this->nullableString($data['target_name'] ?? null) ?? '';
        $snapshot = [
            'name' => $name,
        ];
        if (array_key_exists('target_le_current', $data) && $data['target_le_current'] !== null) {
            $snapshot['le_current'] = (int) $data['target_le_current'];
        }
        if (array_key_exists('target_le_max', $data) && $data['target_le_max'] !== null) {
            $snapshot['le_max'] = (int) $data['target_le_max'];
        }
        if (array_key_exists('armor_protection', $data) && $data['armor_protection'] !== null) {
            $snapshot['armor_protection'] = (int) $data['armor_protection'];
        }

        return CombatTarget::npc($name, $snapshot);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws MagicInvariantViolationException
     */
    private function manualMagicActor(array $data): MagicActor
    {
        $type = (string) ($data['actor_type'] ?? '');

        if ($type === MagicActor::TYPE_CHARACTER) {
            $characterId = $this->nullableInt($data['actor_character_id'] ?? null) ?? 0;
            $character = Character::query()->find($characterId);

            if (! $character instanceof Character) {
                throw MagicInvariantViolationException::actorCharacterMissing();
            }

            return MagicActor::character($character);
        }

        $name = $this->nullableString($data['actor_name'] ?? null) ?? '';
        $snapshot = [
            'name' => $name,
        ];

        if (array_key_exists('actor_ae_current', $data) && $data['actor_ae_current'] !== null) {
            $snapshot['ae_current'] = (int) $data['actor_ae_current'];
        }
        if (array_key_exists('actor_ae_max', $data) && $data['actor_ae_max'] !== null) {
            $snapshot['ae_max'] = (int) $data['actor_ae_max'];
        }

        return MagicActor::npc($name, $snapshot);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws MagicInvariantViolationException
     */
    private function manualMagicTarget(array $data): MagicTarget
    {
        $type = (string) ($data['target_type'] ?? '');

        if ($type === MagicTarget::TYPE_CHARACTER) {
            $characterId = $this->nullableInt($data['target_character_id'] ?? null) ?? 0;
            $character = Character::query()->find($characterId);

            if (! $character instanceof Character) {
                throw MagicInvariantViolationException::targetCharacterMissing();
            }

            return MagicTarget::character($character);
        }

        $name = $this->nullableString($data['target_name'] ?? null) ?? '';
        $snapshot = [
            'name' => $name,
        ];

        if (array_key_exists('target_le_current', $data) && $data['target_le_current'] !== null) {
            $snapshot['le_current'] = (int) $data['target_le_current'];
        }
        if (array_key_exists('target_le_max', $data) && $data['target_le_max'] !== null) {
            $snapshot['le_max'] = (int) $data['target_le_max'];
        }
        if (array_key_exists('target_ae_current', $data) && $data['target_ae_current'] !== null) {
            $snapshot['ae_current'] = (int) $data['target_ae_current'];
        }
        if (array_key_exists('target_ae_max', $data) && $data['target_ae_max'] !== null) {
            $snapshot['ae_max'] = (int) $data['target_ae_max'];
        }

        return MagicTarget::npc($name, $snapshot);
    }

    /**
     * @return array<string, mixed>
     */
    private function combatSnapshotFromConflictActor(SceneConflictActor $conflictActor): array
    {
        return [
            'scene_conflict_actor_id' => (int) $conflictActor->id,
            'name' => $conflictActor->displayName(),
            'le_current' => $this->nullableInt($conflictActor->le_current),
            'le_max' => $this->nullableInt($conflictActor->le_max),
            'ae_current' => $this->nullableInt($conflictActor->ae_current),
            'ae_max' => $this->nullableInt($conflictActor->ae_max),
            'attack_value' => $this->nullableInt($conflictActor->attack_value),
            'defense_value' => $this->nullableInt($conflictActor->defense_value),
            'armor_protection' => $this->nullableInt($conflictActor->armor_protection),
            'damage_value' => $this->nullableInt($conflictActor->damage_value),
            'spell_value' => $this->nullableInt($conflictActor->spell_value),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function magicSnapshotFromConflictActor(SceneConflictActor $conflictActor): array
    {
        return [
            'scene_conflict_actor_id' => (int) $conflictActor->id,
            'name' => $conflictActor->displayName(),
            'le_current' => $this->nullableInt($conflictActor->le_current),
            'le_max' => $this->nullableInt($conflictActor->le_max),
            'ae_current' => $this->nullableInt($conflictActor->ae_current),
            'ae_max' => $this->nullableInt($conflictActor->ae_max),
            'attack_value' => $this->nullableInt($conflictActor->attack_value),
            'defense_value' => $this->nullableInt($conflictActor->defense_value),
            'armor_protection' => $this->nullableInt($conflictActor->armor_protection),
            'damage_value' => $this->nullableInt($conflictActor->damage_value),
            'spell_value' => $this->nullableInt($conflictActor->spell_value),
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
