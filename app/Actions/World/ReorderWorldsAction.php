<?php

declare(strict_types=1);

namespace App\Actions\World;

use App\Models\World;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;

final class ReorderWorldsAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(World $world, string $direction): bool
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Unsupported move direction.');
        }

        $moved = false;

        $this->db->transaction(function () use ($world, $direction, &$moved): void {
            $lockedWorld = $this->lockAndVerifyContext($world);
            $orderedIds = $this->resolveOrderedIds();
            $swappedIds = $this->resolveAndValidateReorder($orderedIds, (int) $lockedWorld->id, $direction);

            if ($swappedIds === $orderedIds) {
                $moved = false;

                return;
            }

            $this->persistOrder($swappedIds);
            $moved = true;
        }, 3);

        return $moved;
    }

    private function lockAndVerifyContext(World $world): World
    {
        /** @var World $lockedWorld */
        $lockedWorld = World::query()
            ->whereKey((int) $world->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedWorld;
    }

    /**
     * @return array<int, int>
     */
    private function resolveOrderedIds(): array
    {
        return World::query()
            ->ordered()
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
    private function resolveAndValidateReorder(array $orderedIds, int $worldId, string $direction): array
    {
        $currentIndex = array_search($worldId, $orderedIds, true);
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
        foreach ($orderedIds as $index => $worldId) {
            World::query()
                ->whereKey($worldId)
                ->update(['position' => ($index + 1) * 10]);
        }
    }
}
