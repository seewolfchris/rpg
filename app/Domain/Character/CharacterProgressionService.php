<?php

namespace App\Domain\Character;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Character;
use App\Models\CharacterProgressionEvent;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CharacterProgressionService
{
    /**
     * @param  array<int, array{character_id: int, xp_delta: int}>  $awards
     * @return array{affected_characters: int, total_xp_delta: int}
     */
    public function awardXpBatch(
        User $actor,
        Campaign $campaign,
        ?Scene $scene,
        string $eventMode,
        array $awards,
        ?string $reason = null,
    ): array {
        $eventType = $this->mapXpEventType($eventMode);
        $normalizedReason = $this->normalizeReason($reason);

        return DB::transaction(function () use ($actor, $campaign, $scene, $eventMode, $eventType, $awards, $normalizedReason): array {
            $participantUserIds = $this->campaignParticipantUserIds($campaign);
            $characterIds = collect($awards)
                ->pluck('character_id')
                ->map(static fn ($id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();

            /** @var Collection<int, Character> $characters */
            $characters = Character::query()
                ->whereIn('id', $characterIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $errors = [];

            foreach ($awards as $index => $award) {
                $characterId = (int) ($award['character_id'] ?? 0);
                $xpDelta = (int) ($award['xp_delta'] ?? 0);
                /** @var Character|null $character */
                $character = $characters->get($characterId);

                if (! $character) {
                    $errors['awards.'.$index.'.character_id'] = 'Der Ziel-Charakter wurde nicht gefunden.';

                    continue;
                }

                if ($xpDelta === 0) {
                    $errors['awards.'.$index.'.xp_delta'] = 'XP-Differenz darf nicht 0 sein.';

                    continue;
                }

                if ($eventMode === 'milestone' && $xpDelta < 0) {
                    $errors['awards.'.$index.'.xp_delta'] = 'Für Meilensteine sind nur positive XP erlaubt.';

                    continue;
                }

                if ((int) $character->world_id !== (int) $campaign->world_id) {
                    $errors['awards.'.$index.'.character_id'] = 'Der Ziel-Charakter gehört nicht zur Welt dieser Kampagne.';

                    continue;
                }

                if (! $participantUserIds->contains((int) $character->user_id)) {
                    $errors['awards.'.$index.'.character_id'] = 'Der Ziel-Charakter ist kein aktiver Teilnehmer dieser Kampagne.';

                    continue;
                }

                $currentXp = max(0, (int) $character->xp_total);
                $currentLevel = max(1, (int) $character->level);
                $nextXp = max(0, $currentXp + $xpDelta);

                if (! $this->allowLevelDown() && $nextXp < $this->xpRequiredForLevel($currentLevel)) {
                    $errors['awards.'.$index.'.xp_delta'] = 'Korrektur würde die aktuelle Stufe unterschreiten und ist nicht erlaubt.';
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $affectedCharacters = 0;
            $totalXpDelta = 0;

            foreach ($awards as $award) {
                $characterId = (int) $award['character_id'];
                $xpDelta = (int) $award['xp_delta'];
                /** @var Character $character */
                $character = $characters->get($characterId);

                $currentXp = max(0, (int) $character->xp_total);
                $currentLevel = max(1, (int) $character->level);
                $currentUnspent = max(0, (int) $character->attribute_points_unspent);

                $nextXp = max(0, $currentXp + $xpDelta);
                $nextLevel = $this->levelForXp($nextXp);
                if (! $this->allowLevelDown()) {
                    $nextLevel = max($currentLevel, $nextLevel);
                }

                $gainedLevels = max(0, $nextLevel - $currentLevel);
                $apGain = $gainedLevels * $this->attributePointsPerLevel();

                $character->xp_total = $nextXp;
                $character->level = $nextLevel;
                $character->attribute_points_unspent = $currentUnspent + $apGain;
                $character->save();

                CharacterProgressionEvent::query()->create([
                    'character_id' => $character->id,
                    'actor_user_id' => $actor->id,
                    'campaign_id' => $campaign->id,
                    'scene_id' => $scene?->id,
                    'event_type' => $eventType,
                    'xp_delta' => $xpDelta,
                    'level_before' => $currentLevel,
                    'level_after' => $nextLevel,
                    'ap_delta' => 0,
                    'attribute_deltas' => null,
                    'reason' => $normalizedReason,
                    'meta' => [
                        'event_mode' => $eventMode,
                    ],
                    'created_at' => now(),
                ]);

                if ($apGain > 0) {
                    CharacterProgressionEvent::query()->create([
                        'character_id' => $character->id,
                        'actor_user_id' => $actor->id,
                        'campaign_id' => $campaign->id,
                        'scene_id' => $scene?->id,
                        'event_type' => CharacterProgressionEvent::EVENT_LEVEL_UP_SYSTEM,
                        'xp_delta' => 0,
                        'level_before' => $currentLevel,
                        'level_after' => $nextLevel,
                        'ap_delta' => $apGain,
                        'attribute_deltas' => null,
                        'reason' => 'Automatischer Stufenaufstieg durch XP-Schwelle.',
                        'meta' => [
                            'gained_levels' => $gainedLevels,
                            'points_per_level' => $this->attributePointsPerLevel(),
                        ],
                        'created_at' => now(),
                    ]);
                }

                $affectedCharacters++;
                $totalXpDelta += $xpDelta;
            }

            return [
                'affected_characters' => $affectedCharacters,
                'total_xp_delta' => $totalXpDelta,
            ];
        });
    }

    /**
     * @param  array<string, int>  $attributeAllocations
     * @return array{spent_points: int, attribute_deltas: array<string, int>}
     */
    public function spendAttributePoints(
        Character $character,
        User $actor,
        array $attributeAllocations,
        ?string $note = null,
    ): array {
        $normalizedReason = $this->normalizeReason($note);

        return DB::transaction(function () use ($character, $actor, $attributeAllocations, $normalizedReason): array {
            /** @var Character|null $lockedCharacter */
            $lockedCharacter = Character::query()
                ->whereKey($character->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedCharacter) {
                throw ValidationException::withMessages([
                    'character' => 'Charakter konnte nicht geladen werden.',
                ]);
            }

            $allocations = $this->normalizeAttributeAllocations($attributeAllocations);
            if ($allocations === []) {
                throw ValidationException::withMessages([
                    'attribute_allocations' => 'Bitte mindestens einen Attributpunkt verteilen.',
                ]);
            }

            $totalPoints = array_sum($allocations);
            $unspentPoints = max(0, (int) $lockedCharacter->attribute_points_unspent);

            if ($totalPoints > $unspentPoints) {
                throw ValidationException::withMessages([
                    'attribute_allocations' => 'Nicht genügend unverteilte Attributpunkte verfügbar.',
                ]);
            }

            $currentLevel = max(1, (int) $lockedCharacter->level);
            $attributeCap = $this->attributeCap();
            $perLevelAttributeCap = $this->attributePerLevelCap();
            $maxSpentPerAttribute = max(0, $currentLevel - 1) * $perLevelAttributeCap;
            $historicalSpent = $this->historicalAttributeSpendTotals($lockedCharacter->id);
            $legacyMap = $this->legacyColumnMap();
            $baseAttributes = $this->resolveBaseAttributes($lockedCharacter);

            $errors = [];
            $nextAttributes = $baseAttributes;

            foreach ($allocations as $attributeKey => $delta) {
                if (! array_key_exists($attributeKey, $baseAttributes)) {
                    $errors['attribute_allocations.'.$attributeKey] = 'Ungültiger Attributschlüssel.';

                    continue;
                }

                $nextValue = $baseAttributes[$attributeKey] + $delta;
                if ($nextValue > $attributeCap) {
                    $errors['attribute_allocations.'.$attributeKey] = 'Attribut-Cap von '.$attributeCap.' überschritten.';
                }

                $alreadySpent = (int) ($historicalSpent[$attributeKey] ?? 0);
                if ($alreadySpent + $delta > $maxSpentPerAttribute) {
                    $errors['attribute_allocations.'.$attributeKey] = 'Dieses Attribut darf auf der aktuellen Stufe insgesamt nur um '.$maxSpentPerAttribute.' Punkte erhöht werden.';
                }

                $nextAttributes[$attributeKey] = $nextValue;
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            foreach ($nextAttributes as $attributeKey => $value) {
                $lockedCharacter->{$attributeKey} = $value;

                $legacyColumn = array_search($attributeKey, $legacyMap, true);
                if (is_string($legacyColumn) && $legacyColumn !== '') {
                    $lockedCharacter->{$legacyColumn} = $value;
                }
            }

            $derivedPools = $this->calculateDerivedPools($lockedCharacter, $nextAttributes);
            $lockedCharacter->le_max = $derivedPools['le_max'];
            $lockedCharacter->ae_max = $derivedPools['ae_max'];
            $lockedCharacter->le_current = $lockedCharacter->le_current === null
                ? $derivedPools['le_max']
                : $this->clampInt((int) $lockedCharacter->le_current, 0, $derivedPools['le_max']);
            $lockedCharacter->ae_current = $lockedCharacter->ae_current === null
                ? $derivedPools['ae_max']
                : $this->clampInt((int) $lockedCharacter->ae_current, 0, $derivedPools['ae_max']);
            $lockedCharacter->attribute_points_unspent = $unspentPoints - $totalPoints;
            $lockedCharacter->save();

            CharacterProgressionEvent::query()->create([
                'character_id' => $lockedCharacter->id,
                'actor_user_id' => $actor->id,
                'campaign_id' => null,
                'scene_id' => null,
                'event_type' => CharacterProgressionEvent::EVENT_AP_SPEND,
                'xp_delta' => 0,
                'level_before' => $currentLevel,
                'level_after' => $currentLevel,
                'ap_delta' => -$totalPoints,
                'attribute_deltas' => $allocations,
                'reason' => $normalizedReason,
                'meta' => [
                    'derived_pools' => $derivedPools,
                ],
                'created_at' => now(),
            ]);

            return [
                'spent_points' => $totalPoints,
                'attribute_deltas' => $allocations,
            ];
        });
    }

    /**
     * @return array{level: int, xp_total: int, xp_current_level_start: int, xp_next_level_threshold: int, xp_to_next_level: int, progress_percent: float, attribute_points_unspent: int}
     */
    public function describe(Character $character): array
    {
        $level = max(1, (int) $character->level);
        $xpTotal = max(0, (int) $character->xp_total);
        $xpCurrentLevelStart = $this->xpRequiredForLevel($level);
        $xpNextLevelThreshold = $this->xpRequiredForLevel($level + 1);
        $xpSpan = max(1, $xpNextLevelThreshold - $xpCurrentLevelStart);
        $xpIntoLevel = max(0, $xpTotal - $xpCurrentLevelStart);

        return [
            'level' => $level,
            'xp_total' => $xpTotal,
            'xp_current_level_start' => $xpCurrentLevelStart,
            'xp_next_level_threshold' => $xpNextLevelThreshold,
            'xp_to_next_level' => max(0, $xpNextLevelThreshold - $xpTotal),
            'progress_percent' => min(100, max(0, round(($xpIntoLevel / $xpSpan) * 100, 2))),
            'attribute_points_unspent' => max(0, (int) $character->attribute_points_unspent),
        ];
    }

    public function levelForXp(int $xpTotal): int
    {
        $safeXp = max(0, $xpTotal);
        $level = 1;

        while ($safeXp >= $this->xpRequiredForLevel($level + 1)) {
            $level++;
        }

        return $level;
    }

    public function xpRequiredForLevel(int $level): int
    {
        $normalizedLevel = max(1, $level);
        if ($normalizedLevel <= 1) {
            return 0;
        }

        $threshold = 0;
        for ($current = 1; $current < $normalizedLevel; $current++) {
            $threshold += $this->xpCostToNextLevel($current);
        }

        return $threshold;
    }

    /**
     * @param  array<string, int>  $attributeAllocations
     * @return array<string, int>
     */
    private function normalizeAttributeAllocations(array $attributeAllocations): array
    {
        $normalized = [];

        foreach ($attributeAllocations as $key => $value) {
            $attributeKey = trim((string) $key);
            if ($attributeKey === '') {
                continue;
            }

            $points = (int) $value;
            if ($points <= 0) {
                continue;
            }

            $normalized[$attributeKey] = $points;
        }

        return $normalized;
    }

    /**
     * @return array<string, int>
     */
    private function historicalAttributeSpendTotals(int $characterId): array
    {
        $attributeKeys = $this->attributeKeys();
        if ($attributeKeys === []) {
            return [];
        }

        try {
            return $this->historicalAttributeSpendTotalsAggregated($characterId, $attributeKeys);
        } catch (\Throwable) {
            return $this->historicalAttributeSpendTotalsFromEvents($characterId);
        }
    }

    /**
     * @param  list<string>  $attributeKeys
     * @return array<string, int>
     */
    private function historicalAttributeSpendTotalsAggregated(int $characterId, array $attributeKeys): array
    {
        $query = CharacterProgressionEvent::query()
            ->where('character_id', $characterId)
            ->where('event_type', CharacterProgressionEvent::EVENT_AP_SPEND);

        $driver = DB::connection($query->getModel()->getConnectionName())->getDriverName();
        $selectFragments = [];
        $bindings = [];

        foreach ($attributeKeys as $key) {
            $attributeKey = trim($key);
            if ($attributeKey === '') {
                continue;
            }

            $path = '$.'.$attributeKey;
            $alias = $this->historicalSpendAlias($attributeKey);

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $selectFragments[] = 'SUM(COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(attribute_deltas, ?)) AS SIGNED), 0)) as '.$alias;
                $bindings[] = $path;

                continue;
            }

            if ($driver === 'sqlite') {
                $selectFragments[] = 'SUM(COALESCE(CAST(json_extract(attribute_deltas, ?) AS INTEGER), 0)) as '.$alias;
                $bindings[] = $path;

                continue;
            }
        }

        if ($selectFragments === []) {
            return $this->historicalAttributeSpendTotalsFromEvents($characterId);
        }

        $totalsRow = $query->selectRaw(implode(', ', $selectFragments), $bindings)->first();
        if (! $totalsRow) {
            return array_fill_keys($attributeKeys, 0);
        }
        $totals = [];

        foreach ($attributeKeys as $key) {
            $alias = $this->historicalSpendAlias($key);
            $totals[$key] = max(0, (int) ($totalsRow->{$alias} ?? 0));
        }

        return $totals;
    }

    /**
     * @return array<string, int>
     */
    private function historicalAttributeSpendTotalsFromEvents(int $characterId): array
    {
        $totals = [];
        $events = CharacterProgressionEvent::query()
            ->where('character_id', $characterId)
            ->where('event_type', CharacterProgressionEvent::EVENT_AP_SPEND)
            ->pluck('attribute_deltas');

        foreach ($events as $eventDeltas) {
            if (! is_array($eventDeltas)) {
                continue;
            }

            foreach ($eventDeltas as $attributeKey => $delta) {
                $key = trim((string) $attributeKey);
                if ($key === '') {
                    continue;
                }

                $totals[$key] = (int) ($totals[$key] ?? 0) + max(0, (int) $delta);
            }
        }

        return $totals;
    }

    /**
     * @return Collection<int, int<1, max>>
     */
    private function campaignParticipantUserIds(Campaign $campaign): Collection
    {
        return $campaign->invitations()
            ->where('status', CampaignInvitation::STATUS_ACCEPTED)
            ->pluck('user_id')
            ->merge([(int) $campaign->owner_id])
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
    }

    private function historicalSpendAlias(string $attributeKey): string
    {
        $suffix = preg_replace('/[^a-z0-9_]/i', '_', strtolower($attributeKey)) ?? 'attribute';

        return 'sum_'.$suffix;
    }

    /**
     * @return array<string, int>
     */
    private function resolveBaseAttributes(Character $character): array
    {
        $attributes = [];

        foreach ($this->attributeKeys() as $key) {
            $value = $character->{$key};
            if ($value !== null) {
                $attributes[$key] = (int) $value;

                continue;
            }

            $legacyColumn = array_search($key, $this->legacyColumnMap(), true);
            if (is_string($legacyColumn) && $legacyColumn !== '' && $character->{$legacyColumn} !== null) {
                $attributes[$key] = $this->convertLegacyValueToPercent((int) $character->{$legacyColumn});

                continue;
            }

            $attributes[$key] = 40;
        }

        return $attributes;
    }

    /**
     * @param  array<string, int>  $baseAttributes
     * @return array{le_max: int, ae_max: int}
     */
    private function calculateDerivedPools(Character $character, array $baseAttributes): array
    {
        $speciesKey = strtolower((string) $character->species);
        $callingKey = strtolower((string) $character->calling);
        $speciesModifiers = (array) data_get($this->characterSheetConfig(), 'species.'.$speciesKey.'.modifiers', []);

        $effectiveAttributes = $baseAttributes;
        foreach ($speciesModifiers as $key => $delta) {
            if (! array_key_exists($key, $effectiveAttributes)) {
                continue;
            }

            $effectiveAttributes[$key] += (int) $delta;
        }

        $leBase = (int) round((($effectiveAttributes['ko'] ?? 0) + ($effectiveAttributes['kk'] ?? 0) + ($effectiveAttributes['mu'] ?? 0)) / 3);
        $aeBase = (int) round((($effectiveAttributes['kl'] ?? 0) + ($effectiveAttributes['in'] ?? 0) + ($effectiveAttributes['ch'] ?? 0)) / 3);

        $le = $leBase + (int) data_get($this->characterSheetConfig(), 'species.'.$speciesKey.'.le_bonus', 0);
        $ae = $aeBase + (int) data_get($this->characterSheetConfig(), 'species.'.$speciesKey.'.ae_bonus', 0);

        $callingBonuses = (array) data_get($this->characterSheetConfig(), 'callings.'.$callingKey.'.bonuses', []);
        $le += (int) Arr::get($callingBonuses, 'le_flat', 0);
        $ae += (int) Arr::get($callingBonuses, 'ae_flat', 0);

        if ($this->hasAstralAccess($speciesKey, $callingKey, $callingBonuses)) {
            $aePercent = (int) Arr::get($callingBonuses, 'ae_percent', 0);
            if ($aePercent > 0) {
                $ae += (int) round($aeBase * ($aePercent / 100));
            }
        } else {
            $ae = 0;
        }

        return [
            'le_max' => max($le, 1),
            'ae_max' => max($ae, 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $callingBonuses
     */
    private function hasAstralAccess(string $speciesKey, string $callingKey, array $callingBonuses): bool
    {
        $magicSpecies = array_map(
            static fn ($value): string => strtolower((string) $value),
            (array) data_get($this->characterSheetConfig(), 'magic_capable_species', [])
        );
        $magicCallings = array_map(
            static fn ($value): string => strtolower((string) $value),
            (array) data_get($this->characterSheetConfig(), 'magic_capable_callings', [])
        );

        if (in_array($speciesKey, $magicSpecies, true) || in_array($callingKey, $magicCallings, true)) {
            return true;
        }

        $speciesAeBonus = (int) data_get($this->characterSheetConfig(), 'species.'.$speciesKey.'.ae_bonus', 0);
        $callingAeFlat = (int) Arr::get($callingBonuses, 'ae_flat', 0);
        $callingAePercent = (int) Arr::get($callingBonuses, 'ae_percent', 0);

        return $speciesAeBonus > 0 || $callingAeFlat > 0 || $callingAePercent > 0;
    }

    private function xpCostToNextLevel(int $currentLevel): int
    {
        $base = (int) config('character_progression.xp_curve.base_cost', 100);
        $exponent = (float) config('character_progression.xp_curve.exponent', 1.35);

        return (int) round($base * pow(max(1, $currentLevel), $exponent));
    }

    private function attributePointsPerLevel(): int
    {
        return max(0, (int) config('character_progression.attribute_points_per_level', 8));
    }

    private function attributeCap(): int
    {
        return max(0, (int) config('character_progression.attribute_cap', 80));
    }

    private function attributePerLevelCap(): int
    {
        return max(0, (int) config('character_progression.attribute_per_level_cap', 4));
    }

    private function allowLevelDown(): bool
    {
        return (bool) config('character_progression.allow_level_down', false);
    }

    private function mapXpEventType(string $eventMode): string
    {
        return match ($eventMode) {
            'milestone' => CharacterProgressionEvent::EVENT_XP_MILESTONE,
            'correction' => CharacterProgressionEvent::EVENT_XP_CORRECTION,
            default => throw ValidationException::withMessages([
                'event_mode' => 'Ungültiger XP-Modus.',
            ]),
        };
    }

    private function normalizeReason(?string $reason): ?string
    {
        $normalized = trim((string) $reason);

        return $normalized !== '' ? $normalized : null;
    }

    private function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($value, $max));
    }

    private function convertLegacyValueToPercent(int $legacyValue): int
    {
        $converted = $legacyValue <= 20
            ? (int) round($legacyValue * 5)
            : $legacyValue;

        return (int) max(30, min(60, $converted));
    }

    /**
     * @return list<string>
     */
    private function attributeKeys(): array
    {
        return array_keys((array) data_get($this->characterSheetConfig(), 'attributes', []));
    }

    /**
     * @return array<string, string>
     */
    private function legacyColumnMap(): array
    {
        return (array) data_get($this->characterSheetConfig(), 'legacy_column_map', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function characterSheetConfig(): array
    {
        $config = config('character_sheet', []);

        return is_array($config) ? $config : [];
    }
}
