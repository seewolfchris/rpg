<?php

declare(strict_types=1);

namespace App\Actions\WorldCharacterOptions;

use App\Models\World;
use App\Models\WorldSpecies;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;

final class MoveWorldSpeciesOptionAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(World $world, WorldSpecies $speciesOption, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Unsupported move direction.');
        }

        $this->db->transaction(function () use ($world, $speciesOption, $direction): void {
            $lockedSpeciesOption = $this->lockAndVerifyContext($world, $speciesOption);
            $orderedIds = $this->resolveOrderedIds($world);
            $swappedIds = $this->resolveAndValidateReorder($orderedIds, (int) $lockedSpeciesOption->id, $direction);

            $this->persistOrder($swappedIds);
        }, 3);
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

    /**
     * @return array<int, int>
     */
    private function resolveOrderedIds(World $world): array
    {
        return WorldSpecies::query()
            ->where('world_id', (int) $world->id)
            ->orderBy('position')
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $orderedIds
     * @return array<int, int>
     */
    private function resolveAndValidateReorder(array $orderedIds, int $optionId, string $direction): array
    {
        $currentIndex = array_search($optionId, $orderedIds, true);

        if ($currentIndex === false) {
            return $orderedIds;
        }

        $targetIndex = $direction === 'up'
            ? $currentIndex - 1
            : $currentIndex + 1;

        if (! isset($orderedIds[$targetIndex])) {
            return $orderedIds;
        }

        [$orderedIds[$currentIndex], $orderedIds[$targetIndex]] = [$orderedIds[$targetIndex], $orderedIds[$currentIndex]];

        return $orderedIds;
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    private function persistOrder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            WorldSpecies::query()
                ->whereKey($id)
                ->update([
                    'position' => ($index + 1) * 10,
                    'updated_at' => now(),
                ]);
        }
    }
}
