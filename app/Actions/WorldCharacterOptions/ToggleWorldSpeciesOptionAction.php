<?php

declare(strict_types=1);

namespace App\Actions\WorldCharacterOptions;

use App\Models\World;
use App\Models\WorldSpecies;
use Illuminate\Database\DatabaseManager;

final class ToggleWorldSpeciesOptionAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(World $world, WorldSpecies $speciesOption): bool
    {
        $nextActive = false;

        $this->db->transaction(function () use ($world, $speciesOption, &$nextActive): void {
            $lockedSpeciesOption = $this->lockAndVerifyContext($world, $speciesOption);

            $nextActive = ! (bool) $lockedSpeciesOption->is_active;
            $this->persistSpecies($lockedSpeciesOption, $nextActive);
        }, 3);

        $speciesOption->refresh();

        return $nextActive;
    }

    private function lockAndVerifyContext(World $world, WorldSpecies $speciesOption): WorldSpecies
    {
        /** @var World $lockedWorld */
        $lockedWorld = World::query()
            ->whereKey((int) $world->id)
            ->lockForUpdate()
            ->firstOrFail();

        /** @var WorldSpecies $lockedSpeciesOption */
        $lockedSpeciesOption = WorldSpecies::query()
            ->whereKey((int) $speciesOption->id)
            ->where('world_id', (int) $lockedWorld->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedSpeciesOption;
    }

    private function persistSpecies(WorldSpecies $speciesOption, bool $nextActive): void
    {
        $speciesOption->forceFill([
            'is_active' => $nextActive,
        ]);
        $speciesOption->save();
    }
}
