<?php

declare(strict_types=1);

namespace App\Services\Character;

use App\Models\Character;
use App\Models\World;
use App\Support\CharacterInventoryService;
use App\Support\CharacterSheetResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type TraitList list<string>
 * @phpstan-type TraitPayload array{
 *     advantages?: TraitList|null,
 *     disadvantages?: TraitList|null
 * }
 * @phpstan-type CharacterTextPayload array{
 *     origin?: string,
 *     species?: string,
 *     calling?: string,
 *     calling_custom_name?: string|null,
 *     calling_custom_description?: string|null,
 *     concept?: string|null,
 *     gm_secret?: string|null,
 *     world_connection?: string|null,
 *     gm_note?: string|null
 * }
 * @phpstan-type CharacterAttributeNotePayload array{
 *     mu_note?: string|null,
 *     kl_note?: string|null,
 *     in_note?: string|null,
 *     ch_note?: string|null,
 *     ff_note?: string|null,
 *     ge_note?: string|null,
 *     ko_note?: string|null,
 *     kk_note?: string|null
 * }
 * @phpstan-type InventoryItem array{
 *     name: string,
 *     quantity: int,
 *     equipped: bool
 * }
 * @phpstan-type ArmorItem array{
 *     name: string,
 *     protection: int,
 *     equipped: bool
 * }
 */
class AttributeNormalizer
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $sheetCacheByWorldId = [];

    public function __construct(
        private readonly CharacterInventoryService $inventoryService,
        private readonly CharacterSheetResolver $characterSheetResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function normalizeForCreate(array $payload): array
    {
        $data = $payload;
        unset($data['avatar'], $data['remove_avatar']);

        $data = $this->backfillLegacyCharacterData($data);
        $data = $this->sanitizePoolState($data);
        $this->assertNormalizedPools($data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function normalizeForUpdate(array $payload, Character $character): array
    {
        $data = $payload;
        unset($data['avatar'], $data['remove_avatar']);

        $data = $this->backfillLegacyCharacterData($data, $character);
        $data = $this->sanitizePoolState($data, $character);
        $this->assertNormalizedPools($data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ModelNotFoundException
     */
    private function backfillLegacyCharacterData(array $data, ?Character $character = null): array
    {
        $characterWorldId = $character instanceof Character
            ? (int) $character->world_id
            : null;
        $worldId = (int) ($data['world_id'] ?? $characterWorldId ?? World::resolveDefaultId());
        $resolvedWorld = World::query()->findOrFail($worldId);
        $sheet = $this->sheetForWorldId((int) $resolvedWorld->id);
        $origins = (array) Arr::get($sheet, 'origins', []);
        $speciesOptions = (array) Arr::get($sheet, 'species', []);
        $callingOptions = (array) Arr::get($sheet, 'callings', []);
        $legacyMap = (array) Arr::get($sheet, 'legacy_column_map', []);

        $defaultOrigin = array_key_exists('native_vhaltor', $origins)
            ? 'native_vhaltor'
            : (($key = array_key_first($origins)) === null ? null : (string) $key);
        $defaultSpecies = array_key_exists('mensch', $speciesOptions)
            ? 'mensch'
            : (($key = array_key_first($speciesOptions)) === null ? null : (string) $key);
        $defaultCalling = array_key_exists('abenteurer', $callingOptions)
            ? 'abenteurer'
            : (($key = array_key_first($callingOptions)) === null ? null : (string) $key);

        $characterOrigin = $character instanceof Character
            ? $character->origin
            : null;
        $characterSpecies = $character instanceof Character
            ? $character->species
            : null;
        $characterCalling = $character instanceof Character
            ? $character->calling
            : null;

        $data = $this->backfillCoreTextFields(
            data: $data,
            characterOrigin: $characterOrigin,
            characterSpecies: $characterSpecies,
            characterCalling: $characterCalling,
            defaultOrigin: $defaultOrigin,
            defaultSpecies: $defaultSpecies,
            defaultCalling: $defaultCalling,
        );
        $data = $this->backfillOptionalTextFields($data, $character);
        $data = $this->backfillAttributeNoteFields($data, $character);

        foreach ($legacyMap as $legacyColumn => $attributeKey) {
            if (! array_key_exists($attributeKey, $data) || $data[$attributeKey] === null) {
                if ($character && $character->{$attributeKey} !== null) {
                    $data[$attributeKey] = (int) $character->{$attributeKey};
                } elseif ($character && $character->{$legacyColumn} !== null) {
                    $data[$attributeKey] = $this->convertLegacyValueToPercent((int) $character->{$legacyColumn});
                }
            }

            if (array_key_exists($attributeKey, $data) && $data[$attributeKey] !== null) {
                $data[$legacyColumn] = (int) $data[$attributeKey];
            }
        }

        /** @var TraitList|null $characterAdvantages */
        $characterAdvantages = $character instanceof Character ? $character->advantages : [];
        /** @var TraitList|null $characterDisadvantages */
        $characterDisadvantages = $character instanceof Character ? $character->disadvantages : [];
        $characterInventory = $character instanceof Character ? $character->inventory : [];
        $characterWeapons = $character instanceof Character ? $character->weapons : [];
        $characterArmors = $character instanceof Character ? $character->armors : [];

        $data['advantages'] = $this->resolveTraitList($data['advantages'] ?? null, $characterAdvantages);
        $data['disadvantages'] = $this->resolveTraitList($data['disadvantages'] ?? null, $characterDisadvantages);
        $data['inventory'] = $this->resolveInventoryList($data['inventory'] ?? null, $characterInventory);
        $data['weapons'] = is_array($data['weapons'] ?? null)
            ? $this->sanitizeWeapons($data['weapons'])
            : $this->sanitizeWeapons($characterWeapons);
        $data['armors'] = $this->resolveArmorList($data['armors'] ?? null, $characterArmors);

        foreach (['le_max', 'le_current', 'ae_max', 'ae_current'] as $poolKey) {
            if (! array_key_exists($poolKey, $data) && $character) {
                $data[$poolKey] = $character->{$poolKey};
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizePoolState(array $data, ?Character $character = null): array
    {
        foreach (['le', 'ae'] as $prefix) {
            $maxKey = $prefix.'_max';
            $currentKey = $prefix.'_current';

            $maxValue = max(0, (int) ($data[$maxKey] ?? 0));
            $existingCurrent = $character?->{$currentKey};

            $data[$maxKey] = $maxValue;
            $data[$currentKey] = $existingCurrent === null
                ? $maxValue
                : $this->clampInt((int) $existingCurrent, 0, $maxValue);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function assertNormalizedPools(array $data): void
    {
        $errors = [];

        foreach (['le', 'ae'] as $prefix) {
            $maxKey = $prefix.'_max';
            $currentKey = $prefix.'_current';

            if (! array_key_exists($maxKey, $data)) {
                $errors[$maxKey] = 'Pool max value is missing.';
            }

            if (! array_key_exists($currentKey, $data)) {
                $errors[$currentKey] = 'Pool current value is missing.';
            }

            $maxValue = (int) ($data[$maxKey] ?? 0);
            $currentValue = (int) ($data[$currentKey] ?? 0);

            if ($maxValue < 0) {
                $errors[$maxKey] = 'Pool max value must be >= 0.';
            }

            if ($currentValue < 0 || $currentValue > $maxValue) {
                $errors[$currentKey] = 'Pool current value must be between 0 and max.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sheetForWorldId(int $worldId): array
    {
        if (! array_key_exists($worldId, $this->sheetCacheByWorldId)) {
            $this->sheetCacheByWorldId[$worldId] = $this->characterSheetResolver->resolveForWorldId($worldId);
        }

        return $this->sheetCacheByWorldId[$worldId];
    }

    private function convertLegacyValueToPercent(int $legacyValue): int
    {
        $converted = $legacyValue <= 20
            ? (int) round($legacyValue * 5)
            : $legacyValue;

        return (int) max(30, min(60, $converted));
    }

    private function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($value, $max));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>&CharacterTextPayload
     */
    private function backfillCoreTextFields(
        array $data,
        ?string $characterOrigin,
        ?string $characterSpecies,
        ?string $characterCalling,
        ?string $defaultOrigin,
        ?string $defaultSpecies,
        ?string $defaultCalling,
    ): array {
        $data['origin'] = (string) ($data['origin'] ?? $characterOrigin ?? $defaultOrigin ?? '');
        $data['species'] = (string) ($data['species'] ?? $characterSpecies ?? $defaultSpecies ?? '');
        $data['calling'] = (string) ($data['calling'] ?? $characterCalling ?? $defaultCalling ?? '');

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>&CharacterTextPayload
     */
    private function backfillOptionalTextFields(array $data, ?Character $character): array
    {
        foreach ([
            'calling_custom_name',
            'calling_custom_description',
            'concept',
            'gm_secret',
            'world_connection',
            'gm_note',
        ] as $key) {
            if (! array_key_exists($key, $data) && $character) {
                /** @var string|null $existingValue */
                $existingValue = $character->{$key};
                $data[$key] = $existingValue;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>&CharacterAttributeNotePayload
     */
    private function backfillAttributeNoteFields(array $data, ?Character $character): array
    {
        foreach ([
            'mu_note',
            'kl_note',
            'in_note',
            'ch_note',
            'ff_note',
            'ge_note',
            'ko_note',
            'kk_note',
        ] as $key) {
            if (array_key_exists($key, $data) || ! $character instanceof Character) {
                continue;
            }

            /** @var string|null $existingValue */
            $existingValue = $character->{$key};
            $data[$key] = $existingValue;
        }

        return $data;
    }

    /**
     * @param  mixed  $incomingTraits
     * @param  TraitList|null  $fallbackTraits
     * @return TraitList|null
     */
    private function resolveTraitList(mixed $incomingTraits, ?array $fallbackTraits): ?array
    {
        if (! is_array($incomingTraits)) {
            return $fallbackTraits;
        }

        /** @var TraitList $normalizedTraits */
        $normalizedTraits = array_values($incomingTraits);

        return $normalizedTraits;
    }

    /**
     * @param  mixed  $incomingInventory
     * @param  mixed  $fallbackInventory
     * @return array<int, InventoryItem>
     */
    private function resolveInventoryList(mixed $incomingInventory, mixed $fallbackInventory): array
    {
        $inventorySource = is_array($incomingInventory)
            ? $incomingInventory
            : $fallbackInventory;

        return $this->inventoryService->normalize($inventorySource);
    }

    /**
     * @param  mixed  $incomingArmors
     * @param  mixed  $fallbackArmors
     * @return array<int, ArmorItem>
     */
    private function resolveArmorList(mixed $incomingArmors, mixed $fallbackArmors): array
    {
        $armorSource = is_array($incomingArmors)
            ? $incomingArmors
            : $fallbackArmors;

        return $this->sanitizeArmors($armorSource);
    }

    /**
     * @return array<int, array{name: string, attack: int, parry: int, damage: int}>
     */
    private function sanitizeWeapons(mixed $weapons): array
    {
        if (! is_array($weapons)) {
            return [];
        }

        $normalized = [];

        foreach ($weapons as $weapon) {
            if (! is_array($weapon)) {
                continue;
            }

            $name = trim((string) ($weapon['name'] ?? ''));
            $damage = $this->normalizeWeaponDamageValue($weapon['damage'] ?? null);
            $attack = (int) ($weapon['attack'] ?? 0);
            $parry = (int) ($weapon['parry'] ?? 0);

            if ($name === '' || $damage <= 0) {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'attack' => max(0, min(100, $attack)),
                'parry' => max(0, min(100, $parry)),
                'damage' => $damage,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int, array{name: string, protection: int, equipped: bool}>
     */
    private function sanitizeArmors(mixed $armors): array
    {
        if (! is_array($armors)) {
            return [];
        }

        $normalized = [];

        foreach ($armors as $armor) {
            if (! is_array($armor) && ! is_string($armor)) {
                continue;
            }

            if (is_string($armor)) {
                $name = trim($armor);
                $protection = 0;
                $equipped = false;
            } else {
                $name = trim((string) ($armor['name'] ?? $armor['item'] ?? ''));
                $protection = (int) ($armor['protection'] ?? $armor['rs'] ?? 0);
                $equipped = (bool) ($armor['equipped'] ?? false);
            }

            if ($name === '') {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'protection' => max(0, min(99, $protection)),
                'equipped' => $equipped,
            ];
        }

        return $normalized;
    }

    private function normalizeWeaponDamageValue(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return max(1, min(999, (int) $value));
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return 0;
        }

        if (preg_match('/^(\d+)\s*[wWdD]\s*(\d+)\s*([+-]\s*\d+)?$/', $raw, $matches) === 1) {
            $count = (int) $matches[1];
            $faces = (int) $matches[2];
            $bonus = (int) str_replace(' ', '', (string) ($matches[3] ?? '0'));
            $estimated = (int) round(($count * (($faces + 1) / 2)) + $bonus);

            return max(1, min(999, $estimated));
        }

        if (preg_match('/-?\d+/', $raw, $matches) === 1) {
            return max(1, min(999, (int) $matches[0]));
        }

        return 0;
    }
}
