<?php

declare(strict_types=1);

namespace App\Domain\Magic;

use App\Domain\Magic\Exceptions\MagicInvariantViolationException;
use App\Models\Character;
use InvalidArgumentException;

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
class MagicEffectApplier
{
    public const EFFECT_LE_DAMAGE = 'le_damage';

    public const EFFECT_LE_HEAL = 'le_heal';

    public const EFFECT_AE_DAMAGE = 'ae_damage';

    public const EFFECT_ATTRIBUTE_DELTA = 'attribute_delta';

    public const EFFECT_NARRATIVE = 'narrative';

    /**
     * @var list<string>
     */
    private const ALLOWED_EFFECT_TYPES = [
        self::EFFECT_LE_DAMAGE,
        self::EFFECT_LE_HEAL,
        self::EFFECT_AE_DAMAGE,
        self::EFFECT_ATTRIBUTE_DELTA,
        self::EFFECT_NARRATIVE,
    ];

    /**
     * @var list<string>
     */
    private const DEFAULT_ATTRIBUTE_KEYS = ['mu', 'kl', 'in', 'ch', 'ff', 'ge', 'ko', 'kk'];

    public function __construct(
        private readonly MagicSnapshotBuilder $snapshotBuilder,
    ) {}

    /**
     * @throws MagicInvariantViolationException
     */
    public function normalizeEffectType(string $effectType): string
    {
        $normalized = trim($effectType);

        if (! in_array($normalized, self::ALLOWED_EFFECT_TYPES, true)) {
            throw MagicInvariantViolationException::effectTypeInvalid($normalized);
        }

        return $normalized;
    }

    /**
     * @throws MagicInvariantViolationException
     */
    public function normalizeTargetAttributeKey(
        string $effectType,
        ?string $targetAttributeKey,
        ?Character $targetCharacter,
    ): ?string {
        if ($effectType !== self::EFFECT_ATTRIBUTE_DELTA) {
            return null;
        }

        $key = $this->trimNullable($targetAttributeKey);
        if ($key === null) {
            throw MagicInvariantViolationException::targetAttributeKeyRequired($effectType);
        }

        if ($targetCharacter instanceof Character) {
            try {
                $targetCharacter->currentAttributeColumn($key);
            } catch (InvalidArgumentException) {
                throw MagicInvariantViolationException::targetAttributeKeyInvalid($key);
            }

            return $key;
        }

        if (! in_array($key, self::DEFAULT_ATTRIBUTE_KEYS, true)) {
            throw MagicInvariantViolationException::targetAttributeKeyInvalid($key);
        }

        return $key;
    }

    public function normalizeEffectAmount(string $effectType, int $effectAmount): int
    {
        return match ($effectType) {
            self::EFFECT_ATTRIBUTE_DELTA => $this->clampInt($effectAmount, -100, 100),
            self::EFFECT_NARRATIVE => $this->clampInt($effectAmount, -999, 999),
            default => $this->clampInt($effectAmount, 0, 999),
        };
    }

    /**
     * @return MagicEffectData
     */
    public function emptyEffectData(string $effectType, int $effectAmount, ?string $targetAttributeKey, bool $wasApplied): array
    {
        return [
            'effect_type' => $effectType,
            'effect_amount' => $effectAmount,
            'was_applied' => $wasApplied,
            'applied_le_delta' => null,
            'resulting_le_current' => null,
            'resulting_le_max' => null,
            'applied_ae_delta' => null,
            'resulting_ae_current' => null,
            'resulting_ae_max' => null,
            'target_attribute_key' => $targetAttributeKey,
            'applied_attribute_delta' => null,
            'resulting_attribute_current' => null,
            'resulting_attribute_max' => null,
        ];
    }

    /**
     * @param  MagicEntityContext  $targetContext
     * @param  array<string, mixed>  $targetSnapshotBefore
     * @return array{effect: MagicEffectData, target_snapshot_after: array<string, mixed>}
     */
    public function applyEffect(
        array $targetContext,
        array $targetSnapshotBefore,
        string $effectType,
        int $effectAmount,
        ?string $targetAttributeKey,
    ): array {
        $targetSnapshotAfter = $targetSnapshotBefore;
        $effectData = $this->emptyEffectData($effectType, $effectAmount, $targetAttributeKey, true);

        $targetCharacter = $targetContext['character'];

        switch ($effectType) {
            case self::EFFECT_LE_DAMAGE:
                if ($targetCharacter instanceof Character) {
                    [$appliedDelta, $resultCurrent, $resultMax] = $this->applyPoolDelta($targetCharacter, 'le', -$effectAmount);
                } else {
                    [$targetSnapshotAfter, $appliedDelta, $resultCurrent, $resultMax] = $this->applyNpcPoolDelta(
                        $targetSnapshotBefore,
                        'le',
                        -$effectAmount,
                    );
                }

                $effectData['applied_le_delta'] = $appliedDelta;
                $effectData['resulting_le_current'] = $resultCurrent;
                $effectData['resulting_le_max'] = $resultMax;
                break;

            case self::EFFECT_LE_HEAL:
                if ($targetCharacter instanceof Character) {
                    [$appliedDelta, $resultCurrent, $resultMax] = $this->applyPoolDelta($targetCharacter, 'le', $effectAmount);
                } else {
                    [$targetSnapshotAfter, $appliedDelta, $resultCurrent, $resultMax] = $this->applyNpcPoolDelta(
                        $targetSnapshotBefore,
                        'le',
                        $effectAmount,
                    );
                }

                $effectData['applied_le_delta'] = $appliedDelta;
                $effectData['resulting_le_current'] = $resultCurrent;
                $effectData['resulting_le_max'] = $resultMax;
                break;

            case self::EFFECT_AE_DAMAGE:
                if ($targetCharacter instanceof Character) {
                    [$appliedDelta, $resultCurrent, $resultMax] = $this->applyPoolDelta($targetCharacter, 'ae', -$effectAmount);
                } else {
                    [$targetSnapshotAfter, $appliedDelta, $resultCurrent, $resultMax] = $this->applyNpcPoolDelta(
                        $targetSnapshotBefore,
                        'ae',
                        -$effectAmount,
                    );
                }

                $effectData['applied_ae_delta'] = $appliedDelta;
                $effectData['resulting_ae_current'] = $resultCurrent;
                $effectData['resulting_ae_max'] = $resultMax;
                break;

            case self::EFFECT_ATTRIBUTE_DELTA:
                if ($targetAttributeKey === null) {
                    break;
                }

                if ($targetCharacter instanceof Character) {
                    [$appliedDelta, $resultCurrent, $resultMax] = $this->applyCharacterAttributeDelta(
                        $targetCharacter,
                        $targetAttributeKey,
                        $effectAmount,
                    );
                } else {
                    [$targetSnapshotAfter, $appliedDelta, $resultCurrent, $resultMax] = $this->applyNpcAttributeDelta(
                        $targetSnapshotBefore,
                        $targetAttributeKey,
                        $effectAmount,
                    );
                }

                $effectData['applied_attribute_delta'] = $appliedDelta;
                $effectData['resulting_attribute_current'] = $resultCurrent;
                $effectData['resulting_attribute_max'] = $resultMax;
                break;

            case self::EFFECT_NARRATIVE:
            default:
                break;
        }

        return [
            'effect' => $effectData,
            'target_snapshot_after' => $targetSnapshotAfter,
        ];
    }

    /**
     * @return array{0: int, 1: int|null, 2: int|null}
     */
    private function applyPoolDelta(Character $character, string $poolPrefix, int $requestedDelta): array
    {
        $maxColumn = $poolPrefix.'_max';
        $currentColumn = $poolPrefix.'_current';

        $rawMax = $character->{$maxColumn};
        $rawCurrent = $character->{$currentColumn};

        if ($rawMax === null && $rawCurrent === null) {
            return [0, null, null];
        }

        $maxValue = max((int) ($rawMax ?? $rawCurrent ?? 0), 0);
        $currentValue = $this->clampInt((int) ($rawCurrent ?? $maxValue), 0, $maxValue);
        $resultingValue = $this->clampInt($currentValue + $requestedDelta, 0, $maxValue);
        $appliedDelta = $resultingValue - $currentValue;

        $rawCurrentInt = $rawCurrent === null ? null : (int) $rawCurrent;
        $needsNormalization = $rawCurrentInt !== null && $rawCurrentInt !== $currentValue;

        if ($appliedDelta !== 0 || $needsNormalization) {
            /** @var int<0, max> $normalizedResultingValue */
            $normalizedResultingValue = max(0, $resultingValue);
            $character->{$currentColumn} = $normalizedResultingValue;
        }

        return [$appliedDelta, $resultingValue, $maxValue];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{0: array<string, mixed>, 1: int|null, 2: int|null, 3: int|null}
     */
    private function applyNpcPoolDelta(array $snapshot, string $poolPrefix, int $requestedDelta): array
    {
        $currentKey = $poolPrefix.'_current';
        $maxKey = $poolPrefix.'_max';

        $rawCurrent = $this->snapshotBuilder->snapshotInt($snapshot, $currentKey);
        $rawMax = $this->snapshotBuilder->snapshotInt($snapshot, $maxKey);

        if ($rawCurrent === null && $rawMax === null) {
            return [$snapshot, null, null, null];
        }

        $maxValue = max($rawMax ?? $rawCurrent ?? 0, 0);
        $currentValue = $this->clampInt((int) ($rawCurrent ?? $maxValue), 0, $maxValue);
        $resultingValue = $this->clampInt($currentValue + $requestedDelta, 0, $maxValue);
        $appliedDelta = $resultingValue - $currentValue;

        $snapshot[$currentKey] = $resultingValue;
        if ($rawMax !== null) {
            $snapshot[$maxKey] = $maxValue;
        }

        return [$snapshot, $appliedDelta, $resultingValue, $rawMax !== null ? $maxValue : null];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function applyCharacterAttributeDelta(Character $character, string $attributeKey, int $requestedDelta): array
    {
        $maxValue = $character->effectiveAttributeMax($attributeKey);
        $currentValue = $character->currentAttributeValue($attributeKey);
        $resultingValue = $this->clampInt($currentValue + $requestedDelta, 0, $maxValue);
        $appliedDelta = $resultingValue - $currentValue;

        $column = $character->currentAttributeColumn($attributeKey);
        $rawCurrent = $character->getAttributeValue($column);
        $rawCurrentInt = $rawCurrent === null ? null : (int) $rawCurrent;
        $needsNormalization = $rawCurrentInt !== null && $rawCurrentInt !== $currentValue;

        if ($appliedDelta !== 0 || $needsNormalization) {
            $character->setAttribute($column, $resultingValue);
        }

        return [$appliedDelta, $resultingValue, $maxValue];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{0: array<string, mixed>, 1: int|null, 2: int|null, 3: int|null}
     */
    private function applyNpcAttributeDelta(array $snapshot, string $attributeKey, int $requestedDelta): array
    {
        $currentKey = $attributeKey.'_current';
        $maxValue = $this->snapshotBuilder->snapshotInt($snapshot, $attributeKey);
        $currentValue = $this->snapshotBuilder->snapshotInt($snapshot, $currentKey);

        if ($currentValue === null && $maxValue === null) {
            return [$snapshot, null, null, null];
        }

        $effectiveMax = max($maxValue ?? $currentValue ?? 0, 0);
        $effectiveCurrent = $this->clampInt((int) ($currentValue ?? $effectiveMax), 0, $effectiveMax);
        $resultingValue = $this->clampInt($effectiveCurrent + $requestedDelta, 0, $effectiveMax);

        $snapshot[$currentKey] = $resultingValue;
        $snapshot[$attributeKey] = $effectiveMax;

        return [$snapshot, $resultingValue - $effectiveCurrent, $resultingValue, $effectiveMax];
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
