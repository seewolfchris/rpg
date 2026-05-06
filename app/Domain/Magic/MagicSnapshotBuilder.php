<?php

declare(strict_types=1);

namespace App\Domain\Magic;

use App\Models\Character;

class MagicSnapshotBuilder
{
    /**
     * @param  array<string, mixed>  $seed
     * @return array<string, mixed>
     */
    public function buildCharacterSnapshot(Character $character, array $seed = []): array
    {
        $leMaxRaw = $character->le_max;
        $leMax = $leMaxRaw === null ? null : max(0, (int) $leMaxRaw);
        $leCurrentRaw = $character->le_current;
        $leCurrent = $leCurrentRaw === null
            ? $leMax
            : ($leMax === null
                ? max(0, (int) $leCurrentRaw)
                : $this->clampInt((int) $leCurrentRaw, 0, $leMax));

        $aeMaxRaw = $character->ae_max;
        $aeMax = $aeMaxRaw === null ? null : max(0, (int) $aeMaxRaw);
        $aeCurrentRaw = $character->ae_current;
        $aeCurrent = $aeCurrentRaw === null
            ? $aeMax
            : ($aeMax === null
                ? max(0, (int) $aeCurrentRaw)
                : $this->clampInt((int) $aeCurrentRaw, 0, $aeMax));

        $snapshot = $seed;
        $snapshot['character_id'] = (int) $character->id;
        $snapshot['name'] = (string) $character->name;
        $snapshot['le_current'] = $leCurrent;
        $snapshot['le_max'] = $leMax;
        $snapshot['ae_current'] = $aeCurrent;
        $snapshot['ae_max'] = $aeMax;
        $snapshot['armor_rs'] = max(0, $character->armorProtectionValue());

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $seed
     * @return array<string, mixed>
     */
    public function buildNpcSnapshot(string $name, array $seed = []): array
    {
        $snapshot = $seed;
        $snapshot['name'] = $name;

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function normalizeSnapshot(mixed $snapshot): array
    {
        return is_array($snapshot) ? $snapshot : [];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function snapshotInt(array $snapshot, string $key): ?int
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

    private function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($value, $max));
    }
}
