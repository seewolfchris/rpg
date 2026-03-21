<?php

namespace App\Support;

use App\Models\World;
use App\Models\WorldCalling;
use App\Models\WorldSpecies;

class CharacterSheetResolver
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $baseSheetCache = null;

    /**
     * @return array<string, mixed>
     */
    public function resolveForWorldId(?int $worldId): array
    {
        $resolvedWorldId = $worldId && $worldId > 0
            ? $worldId
            : World::resolveDefaultId();

        $sheet = $this->baseSheet();

        $speciesRows = WorldSpecies::query()
            ->where('world_id', $resolvedWorldId)
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $callingsRows = WorldCalling::query()
            ->where('world_id', $resolvedWorldId)
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $species = [];
        $magicSpecies = [];

        foreach ($speciesRows as $row) {
            $species[$row->key] = [
                'label' => (string) $row->label,
                'description' => (string) ($row->description ?? ''),
                'modifiers' => (array) ($row->modifiers_json ?? []),
                'le_bonus' => (int) $row->le_bonus,
                'ae_bonus' => (int) $row->ae_bonus,
            ];

            if ($row->is_magic_capable) {
                $magicSpecies[] = (string) $row->key;
            }
        }

        $callings = [];
        $magicCallings = [];

        foreach ($callingsRows as $row) {
            $callings[$row->key] = [
                'label' => (string) $row->label,
                'description' => (string) ($row->description ?? ''),
                'minimums' => (array) ($row->minimums_json ?? []),
                'bonuses' => (array) ($row->bonuses_json ?? []),
                'custom' => (bool) $row->is_custom,
            ];

            if ($row->is_magic_capable) {
                $magicCallings[] = (string) $row->key;
            }
        }

        $sheet['species'] = $species;
        $sheet['callings'] = $callings;
        $sheet['magic_capable_species'] = array_values(array_unique($magicSpecies));
        $sheet['magic_capable_callings'] = array_values(array_unique($magicCallings));
        $sheet['world_options_ready'] = $species !== [] && $callings !== [];

        return $sheet;
    }

    /**
     * @param  iterable<int, World>  $worlds
     * @return array<int, array<string, mixed>>
     */
    public function resolveForWorlds(iterable $worlds): array
    {
        $resolved = [];

        foreach ($worlds as $world) {
            if (! $world instanceof World) {
                continue;
            }

            $resolved[(int) $world->id] = $this->resolveForWorldId((int) $world->id);
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseSheet(): array
    {
        if ($this->baseSheetCache !== null) {
            return $this->baseSheetCache;
        }

        /** @var array<string, mixed> $base */
        $base = (array) config('character_sheet', []);

        $this->baseSheetCache = array_merge($base, [
            'species' => [],
            'callings' => [],
            'magic_capable_species' => [],
            'magic_capable_callings' => [],
        ]);

        return $this->baseSheetCache;
    }
}
